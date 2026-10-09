<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Mining\Domain\Models\AmdalComplianceMilestone;
use Modules\Mining\Domain\Models\ReclamationProvision;
use Modules\Mining\Domain\Models\WaterMonitoringReading;
use RuntimeException;

class MiningEnvironmentalComplianceService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function accrueReclamationProvision(string $siteId, float $hectares, int $estimatedCostIdr): ReclamationProvision
    {
        $planCode = 'REC-PLAN-'.strtoupper(Str::random(8));

        return DB::transaction(function () use ($siteId, $hectares, $estimatedCostIdr, $planCode) {
            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'MINING_RECLAMATION_PROVISION_ACCRUAL',
                description: "Mine site reclamation provision accrual for {$planCode}",
                idempotencyKey: 'MIN-REC-ACC-'.$planCode,
                entries: [
                    PostingEntryDTO::forCode('min:reclamation_expense:IDR', 'IDR', $estimatedCostIdr),
                    PostingEntryDTO::forCode('min:reclamation_provision_liability:IDR', 'IDR', -$estimatedCostIdr),
                ],
                referenceType: 'RECLAMATION_PLAN',
                referenceId: $planCode,
            ));

            return ReclamationProvision::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'plan_code' => $planCode,
                'target_hectares' => $hectares,
                'provision_accrued_idr' => $estimatedCostIdr,
                'provision_released_idr' => 0,
                'verified_growth_ndvi' => 0.0,
                'status' => 'ACCRUED',
                'accrual_ledger_tx_id' => $tx->id,
            ]);
        });
    }

    public function releaseReclamationProvision(string $provisionId, float $verifiedNdvi): ReclamationProvision
    {
        $provision = ReclamationProvision::findOrFail($provisionId);

        if ($verifiedNdvi < 0.60) {
            throw new RuntimeException("Reclamation release rejected: NDVI vegetative growth {$verifiedNdvi} below regulatory threshold of 0.60.");
        }

        $releaseAmount = $provision->provision_accrued_idr - $provision->provision_released_idr;
        if ($releaseAmount <= 0) {
            throw new RuntimeException("Reclamation provision for {$provision->plan_code} has already been fully released.");
        }

        return DB::transaction(function () use ($provision, $verifiedNdvi, $releaseAmount) {
            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'MINING_RECLAMATION_PROVISION_RELEASE',
                description: "Reclamation provision release upon verified revegetation for {$provision->plan_code}",
                idempotencyKey: 'MIN-REC-REL-'.$provision->plan_code,
                entries: [
                    PostingEntryDTO::forCode('min:reclamation_provision_liability:IDR', 'IDR', $releaseAmount),
                    PostingEntryDTO::forCode('min:reclamation_expense:IDR', 'IDR', -$releaseAmount),
                ],
                referenceType: 'RECLAMATION_PLAN',
                referenceId: $provision->plan_code,
            ));

            $provision->update([
                'provision_released_idr' => $provision->provision_released_idr + $releaseAmount,
                'verified_growth_ndvi' => $verifiedNdvi,
                'status' => 'RELEASED',
                'release_ledger_tx_id' => $tx->id,
            ]);

            return $provision;
        });
    }

    public function logWaterMonitoring(array $params): WaterMonitoringReading
    {
        $siteId = $params['site_id'];
        $m3 = (float) $params['water_volume_m3'];
        $ph = (float) $params['effluent_ph'];
        $tss = (float) $params['effluent_tss_mg_l'];
        $ratePerM3 = (int) ($params['water_fee_rate_per_m3_minor'] ?? 5000); // 5,000 IDR / m3

        $waterFee = (int) round($m3 * $ratePerM3);

        // Environmental standard: pH between 6.0 and 9.0, TSS <= 200 mg/L
        $thresholdExceeded = ($ph < 6.0 || $ph > 9.0 || $tss > 200.0);
        $penaltyAmount = $thresholdExceeded ? (int) ($params['penalty_minor'] ?? 50000000) : 0;

        return DB::transaction(function () use ($params, $siteId, $m3, $ph, $tss, $waterFee, $thresholdExceeded, $penaltyAmount) {
            $entries = [
                PostingEntryDTO::forCode('min:water_treatment_expense:IDR', 'IDR', $waterFee),
                PostingEntryDTO::forCode('min:water_fee_payable:IDR', 'IDR', -$waterFee),
            ];

            if ($thresholdExceeded && $penaltyAmount > 0) {
                $entries[] = PostingEntryDTO::forCode('min:environmental_penalty_expense:IDR', 'IDR', $penaltyAmount);
                $entries[] = PostingEntryDTO::forCode('min:environmental_penalty_payable:IDR', 'IDR', -$penaltyAmount);
            }

            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'MINING_WATER_MONITORING_SETTLEMENT',
                description: "Water usage fee and compliance audit for {$siteId}",
                idempotencyKey: 'MIN-WTR-'.Str::random(10),
                entries: $entries,
                referenceType: 'WATER_READING',
                referenceId: $params['sampling_point'],
            ));

            return WaterMonitoringReading::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'sampling_point' => $params['sampling_point'],
                'water_volume_m3' => $m3,
                'effluent_ph' => $ph,
                'effluent_tss_mg_l' => $tss,
                'threshold_exceeded' => $thresholdExceeded,
                'environmental_penalty_minor' => $penaltyAmount,
                'water_fee_minor' => $waterFee,
                'ledger_transaction_id' => $tx->id,
                'sampled_at' => Carbon::now(),
            ]);
        });
    }

    public function recordAmdalMilestone(string $siteId, string $docType, string $approvalNumber): AmdalComplianceMilestone
    {
        return AmdalComplianceMilestone::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteId,
            'milestone_code' => 'AMDAL-'.strtoupper(Str::random(8)),
            'document_type' => $docType,
            'regulator_approval_number' => $approvalNumber,
            'is_fully_approved' => true,
            'approved_at' => Carbon::now(),
        ]);
    }

    public function assertSiteOperationAuthorized(string $siteId): bool
    {
        $hasApprovedAmdal = AmdalComplianceMilestone::where('site_id', $siteId)
            ->where('document_type', 'AMDAL')
            ->where('is_fully_approved', true)
            ->exists();

        if (! $hasApprovedAmdal) {
            throw new RuntimeException("Site {$siteId} operation unauthorized: mandatory AMDAL license milestone not fulfilled.");
        }

        return true;
    }
}
