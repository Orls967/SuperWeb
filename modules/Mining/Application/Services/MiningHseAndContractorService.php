<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Mining\Domain\Models\ContractorSafetyEvaluation;
use Modules\Mining\Domain\Models\FatigueLog;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Modules\Mining\Domain\Models\SafetyIncident;
use RuntimeException;

class MiningHseAndContractorService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function issueWorkPermit(array $params): MiningWorkPermit
    {
        return MiningWorkPermit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $params['site_id'],
            'permit_number' => $params['permit_number'] ?? 'PTW-'.strtoupper(Str::random(8)),
            'permit_type' => $params['permit_type'], // HOT_WORK, CONFINED_SPACE, HEIGHT, LOTO
            'supervisor_name' => $params['supervisor_name'] ?? 'HSE-Lead-Officer',
            'worker_party_id' => $params['worker_party_id'],
            'allowed_pit_id' => $params['allowed_pit_id'] ?? null,
            'allowed_latitude' => $params['allowed_latitude'] ?? null,
            'allowed_longitude' => $params['allowed_longitude'] ?? null,
            'allowed_radius_meters' => $params['allowed_radius_meters'] ?? 100.0,
            'status' => 'ACTIVE',
            'valid_from' => Carbon::parse($params['valid_from']),
            'valid_until' => Carbon::parse($params['valid_until']),
        ]);
    }

    /**
     * Validates if a worker can perform critical work in an area at a given time and coordinates.
     */
    public function validatePermitAccess(string $permitId, float $lat, float $lon, ?Carbon $now = null): bool
    {
        $permit = MiningWorkPermit::findOrFail($permitId);
        $currentTime = $now ?? Carbon::now();

        if ($currentTime->gt($permit->valid_until) || $currentTime->lt($permit->valid_from)) {
            $permit->update(['status' => 'EXPIRED']);
            throw new RuntimeException("Work permit {$permit->permit_number} has expired. Access revoked.");
        }

        if ($permit->status !== 'ACTIVE') {
            throw new RuntimeException("Work permit {$permit->permit_number} is not active (Status: {$permit->status}). Access denied.");
        }

        if ($permit->allowed_latitude !== null && $permit->allowed_longitude !== null) {
            $distanceMeters = $this->calculateDistanceMeters(
                $lat,
                $lon,
                $permit->allowed_latitude,
                $permit->allowed_longitude
            );

            if ($distanceMeters > $permit->allowed_radius_meters) {
                throw new RuntimeException("Worker outside authorized permit zone ({$distanceMeters}m > {$permit->allowed_radius_meters}m). Action blocked.");
            }
        }

        return true;
    }

    public function assessOperatorFatigue(array $params): FatigueLog
    {
        $continuousHours = (float) $params['work_hours_continuous'];
        $sleepHours = (float) $params['sleep_hours_prior'];

        // Formula: fatigue increases with long shift hours and insufficient sleep
        $score = (int) round(($continuousHours * 7.5) + ((8.0 - min(8.0, $sleepHours)) * 10));
        $score = max(0, min(100, $score));

        $isCritical = $score >= 70 || $continuousHours > 12.0 || $sleepHours < 4.0;
        $action = $isCritical ? 'MANDATORY_REST' : ($score > 40 ? 'REASSIGN' : 'FIT_FOR_DUTY');

        return FatigueLog::create([
            'id' => (string) Str::uuid(),
            'site_id' => $params['site_id'],
            'operator_party_id' => $params['operator_party_id'],
            'equipment_id' => $params['equipment_id'] ?? null,
            'work_hours_continuous' => $continuousHours,
            'sleep_hours_prior' => $sleepHours,
            'fatigue_score' => $score,
            'is_critical' => $isCritical,
            'recommended_action' => $action,
            'logged_at' => Carbon::now(),
        ]);
    }

    public function assignHeavyEquipmentOperator(string $fatigueLogId): void
    {
        $log = FatigueLog::findOrFail($fatigueLogId);
        if ($log->is_critical || $log->recommended_action === 'MANDATORY_REST') {
            throw new RuntimeException("Operator {$log->operator_party_id} is critically fatigued (Score: {$log->fatigue_score}). Heavy equipment assignment rejected per UU 22/2009.");
        }
    }

    public function reportSafetyIncident(array $params): SafetyIncident
    {
        $points = match ($params['incident_type']) {
            'NEAR_MISS' => 15,
            'HAZARD_ID' => 20,
            'PPE_NONCOMPLIANCE' => 5,
            default => 10,
        };

        return SafetyIncident::create([
            'id' => (string) Str::uuid(),
            'site_id' => $params['site_id'],
            'incident_type' => $params['incident_type'],
            'severity' => $params['severity'] ?? 'LOW',
            'leading_indicator_points' => $points,
            'reporter_party_id' => $params['is_anonymous'] ? null : ($params['reporter_party_id'] ?? null),
            'is_anonymous' => $params['is_anonymous'] ?? false,
            'description' => $params['description'],
            'reported_at' => Carbon::now(),
        ]);
    }

    public function evaluateContractorSafety(string $contractorPartyId, int $safetyScore, int $incidentCount, int $penaltyAmountMinor = 0): ContractorSafetyEvaluation
    {
        $tier = match (true) {
            $safetyScore >= 80 && $incidentCount === 0 => 'PREFERRED',
            $safetyScore >= 50 && $incidentCount < 3 => 'PROBATION',
            default => 'BLACKLISTED',
        };

        $tenderEligible = $tier !== 'BLACKLISTED';
        $txId = null;

        if ($penaltyAmountMinor > 0) {
            $ledgerTx = $this->ledgerService->post(new PostingDTO(
                type: 'MINING_CONTRACTOR_PENALTY',
                description: "Contractor safety violation penalty for {$contractorPartyId}",
                idempotencyKey: 'CNTR-PEN-'.Str::random(10),
                entries: [
                    PostingEntryDTO::forCode('min:contractor_receivable:IDR', 'IDR', $penaltyAmountMinor),
                    PostingEntryDTO::forCode('min:safety_penalty_revenue:IDR', 'IDR', -$penaltyAmountMinor),
                ],
                referenceType: 'CONTRACTOR_SAFETY_EVALUATION',
                referenceId: $contractorPartyId,
            ));
            $txId = $ledgerTx->id;
        }

        return ContractorSafetyEvaluation::create([
            'id' => (string) Str::uuid(),
            'contractor_party_id' => $contractorPartyId,
            'safety_score' => $safetyScore,
            'total_incidents' => $incidentCount,
            'tier' => $tier,
            'tender_eligible' => $tenderEligible,
            'penalty_amount_minor' => $penaltyAmountMinor,
            'penalty_ledger_tx_id' => $txId,
        ]);
    }

    protected function calculateDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $latDiff = deg2rad($lat2 - $lat1);
        $lonDiff = deg2rad($lon2 - $lon1);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
