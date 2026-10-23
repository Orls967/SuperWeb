<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\AmdalComplianceMilestone;
use Modules\Mining\Domain\Models\ReclamationProvision;
use Modules\Mining\Domain\Models\WaterMonitoringReading;
use Modules\Shared\Application\BaseAction;

class VerifyTailingsWaterQualityAndBiodiversityRehabilitationAction extends BaseAction
{
    /**
     * Monitor tailings effluent water quality, assess environmental fines, and track reclamation provision lifecycle.
     *
     * @return array{water_reading: WaterMonitoringReading, threshold_exceeded: bool, reclamation: ReclamationProvision, amdal: AmdalComplianceMilestone}
     */
    public function handle(
        string $siteId,
        string $samplingPoint = 'TAILINGS_POND_DISCHARGE_01',
        float $waterVolumeM3 = 45000.0,
        float $effluentPh = 7.4,
        float $effluentTssMgL = 65.0,
        int $tariffPerM3Idr = 2500,
        string $planCode = 'REC-YEAR-2026',
        float $targetHectares = 150.0,
        int $provisionAccruedIdr = 5000000000,
        float $growthNdvi = 0.72,
        string $milestoneCode = 'AMDAL-RKL-RPL-01'
    ): array {
        return DB::transaction(function () use (
            $siteId,
            $samplingPoint,
            $waterVolumeM3,
            $effluentPh,
            $effluentTssMgL,
            $tariffPerM3Idr,
            $planCode,
            $targetHectares,
            $provisionAccruedIdr,
            $growthNdvi,
            $milestoneCode
        ) {
            // 124.2 Tailings Effluent Quality Monitoring
            $thresholdExceeded = ($effluentPh < 6.0 || $effluentPh > 9.0 || $effluentTssMgL > 100.0);
            $penaltyMinor = $thresholdExceeded ? 500000000 : 0;
            $waterFeeMinor = (int) round($waterVolumeM3 * $tariffPerM3Idr);

            $waterReading = WaterMonitoringReading::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'sampling_point' => $samplingPoint,
                'water_volume_m3' => $waterVolumeM3,
                'effluent_ph' => $effluentPh,
                'effluent_tss_mg_l' => $effluentTssMgL,
                'threshold_exceeded' => $thresholdExceeded,
                'environmental_penalty_minor' => $penaltyMinor,
                'water_fee_minor' => $waterFeeMinor,
                'ledger_transaction_id' => 'TX-WTR-'.strtoupper(bin2hex(random_bytes(4))),
                'sampled_at' => Carbon::now(),
            ]);

            // 124.1 Reclamation provision lifecycle & verification
            $releasedProvisionIdr = ($growthNdvi >= 0.70) ? $provisionAccruedIdr : 0;
            $status = ($growthNdvi >= 0.70) ? 'VERIFIED_RELEASED' : 'ACCRUED';

            $reclamation = ReclamationProvision::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'plan_code' => $planCode,
                'target_hectares' => $targetHectares,
                'provision_accrued_idr' => $provisionAccruedIdr,
                'provision_released_idr' => $releasedProvisionIdr,
                'verified_growth_ndvi' => $growthNdvi,
                'status' => $status,
                'accrual_ledger_tx_id' => 'TX-REC-ACCRUAL-'.strtoupper(bin2hex(random_bytes(4))),
                'release_ledger_tx_id' => ($growthNdvi >= 0.70) ? 'TX-REC-RELEASE-'.strtoupper(bin2hex(random_bytes(4))) : null,
            ]);

            // 124.4 AMDAL Statutory Compliance
            $amdal = AmdalComplianceMilestone::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'milestone_code' => $milestoneCode,
                'document_type' => 'RKL_RPL_SEMESTER_AUDIT',
                'regulator_approval_number' => 'KLHK-SK-'.strtoupper(bin2hex(random_bytes(4))),
                'is_fully_approved' => true,
                'approved_at' => Carbon::now(),
            ]);

            $this->audit(
                action: 'mining.environmental.verified',
                auditable: $waterReading,
                oldValues: null,
                newValues: [
                    'site_id' => $siteId,
                    'effluent_ph' => $effluentPh,
                    'threshold_exceeded' => $thresholdExceeded,
                    'reclamation_status' => $status,
                    'provision_released_idr' => $releasedProvisionIdr,
                ],
                correlationId: "min_env_{$samplingPoint}",
                impactType: 'tailings_biodiversity_compliance'
            );

            return [
                'water_reading' => $waterReading,
                'threshold_exceeded' => $thresholdExceeded,
                'reclamation' => $reclamation,
                'amdal' => $amdal,
            ];
        });
    }
}
