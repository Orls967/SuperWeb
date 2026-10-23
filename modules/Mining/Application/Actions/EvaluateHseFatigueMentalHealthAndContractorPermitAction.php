<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\ContractorSafetyEvaluation;
use Modules\Mining\Domain\Models\FatigueLog;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class EvaluateHseFatigueMentalHealthAndContractorPermitAction extends BaseAction
{
    /**
     * Evaluate work permit geofence and validity, assess operator fatigue safety, and score contractor HSE prequalification.
     *
     * @return array{permit: MiningWorkPermit, fatigue_log: FatigueLog, contractor_eval: ContractorSafetyEvaluation, fatigue_score: int}
     */
    public function handle(
        string $siteId,
        string $permitType,
        string $workerPartyId,
        string $validFrom,
        string $validUntil,
        float $workerLat,
        float $workerLng,
        float $allowedLat,
        float $allowedLng,
        float $allowedRadiusMeters = 100.0,
        float $continuousWorkHours = 6.0,
        float $priorSleepHours = 7.0,
        string $contractorPartyId = 'CONT-PT-001',
        int $contractorIncidents = 0
    ): array {
        return DB::transaction(function () use (
            $siteId,
            $permitType,
            $workerPartyId,
            $validFrom,
            $validUntil,
            $workerLat,
            $workerLng,
            $allowedLat,
            $allowedLng,
            $allowedRadiusMeters,
            $continuousWorkHours,
            $priorSleepHours,
            $contractorPartyId,
            $contractorIncidents
        ) {
            $currentTime = Carbon::now();
            $validFromTime = Carbon::parse($validFrom);
            $validUntilTime = Carbon::parse($validUntil);

            // 120.2 Work permit issuance
            $permitNumber = 'PTW-'.strtoupper(bin2hex(random_bytes(4)));

            $permit = MiningWorkPermit::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'permit_number' => $permitNumber,
                'permit_type' => $permitType,
                'supervisor_name' => 'HSE-Lead-Officer',
                'worker_party_id' => $workerPartyId,
                'allowed_latitude' => $allowedLat,
                'allowed_longitude' => $allowedLng,
                'allowed_radius_meters' => $allowedRadiusMeters,
                'valid_from' => $validFromTime,
                'valid_until' => $validUntilTime,
                'status' => 'ACTIVE',
            ]);

            // Validate permit validity
            if ($currentTime->gt($validUntilTime) || $currentTime->lt($validFromTime)) {
                $permit->update(['status' => 'EXPIRED']);
                throw new RuntimeException("Work permit {$permit->permit_number} has expired. Access revoked.");
            }

            // Validate worker coordinates within authorized permit radius
            $distanceMeters = $this->calculateDistanceMeters(
                $workerLat,
                $workerLng,
                $allowedLat,
                $allowedLng
            );

            if ($distanceMeters > $allowedRadiusMeters) {
                throw new RuntimeException("Worker outside authorized permit zone ({$distanceMeters}m > {$allowedRadiusMeters}m). Action blocked.");
            }

            // 120.3 Fatigue management
            $fatigueScore = min(100, max(0, (int) round(($continuousWorkHours * 8.5) - ($priorSleepHours * 6.0) + 20)));
            $isCritical = ($fatigueScore >= 80);

            $fatigueLog = FatigueLog::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'operator_party_id' => $workerPartyId,
                'equipment_id' => 'EXCAVATOR-CAT-390',
                'work_hours_continuous' => $continuousWorkHours,
                'sleep_hours_prior' => $priorSleepHours,
                'fatigue_score' => $fatigueScore,
                'is_critical' => $isCritical,
                'recommended_action' => $isCritical ? 'MANDATORY_REST_RELIEF' : 'FIT_FOR_DUTY',
                'logged_at' => $currentTime,
            ]);

            if ($isCritical) {
                throw new RuntimeException("Critical operator fatigue score ({$fatigueScore} >= 80): Heavy equipment operation prohibited.");
            }

            // 120.5 Contractor Safety Management
            $safetyScore = max(0, min(100, 100 - ($contractorIncidents * 15)));
            $tier = match (true) {
                $safetyScore >= 85 => 'PREFERRED',
                $safetyScore >= 70 => 'QUALIFIED',
                $safetyScore >= 50 => 'PROBATION',
                default => 'BLACKLISTED',
            };
            $tenderEligible = ($safetyScore >= 70);

            $contractorEval = ContractorSafetyEvaluation::create([
                'id' => (string) Str::uuid(),
                'contractor_party_id' => $contractorPartyId,
                'safety_score' => $safetyScore,
                'total_incidents' => $contractorIncidents,
                'tier' => $tier,
                'tender_eligible' => $tenderEligible,
                'penalty_amount_minor' => ($contractorIncidents * 10000000),
            ]);

            $this->audit(
                action: 'mining.hse.permit_fatigue_evaluated',
                auditable: $permit,
                oldValues: null,
                newValues: [
                    'permit_number' => $permitNumber,
                    'worker_party_id' => $workerPartyId,
                    'fatigue_score' => $fatigueScore,
                    'contractor_party_id' => $contractorPartyId,
                    'contractor_tier' => $tier,
                ],
                correlationId: "min_ptw_{$permitNumber}",
                impactType: 'hse_safety_compliance'
            );

            return [
                'permit' => $permit,
                'fatigue_log' => $fatigueLog,
                'contractor_eval' => $contractorEval,
                'fatigue_score' => $fatigueScore,
            ];
        });
    }

    private function calculateDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
