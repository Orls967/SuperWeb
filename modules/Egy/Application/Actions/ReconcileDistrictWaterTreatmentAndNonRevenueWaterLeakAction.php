<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class ReconcileDistrictWaterTreatmentAndNonRevenueWaterLeakAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Reconciles water distribution balance, detects non-revenue water leakage, and settles consolidated district utility invoices.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $batchCode = (string) ($params['batch_code'] ?? 'WTR-'.hash('sha256', (string) ($params['district_id'] ?? 'default')));
            $districtId = (string) ($params['district_id'] ?? 'DISTRICT-CENTRAL');

            $inflowM3 = (int) ($params['water_inflow_m3'] ?? 0);
            $billedM3 = (int) ($params['water_billed_m3'] ?? 0);

            if ($billedM3 > $inflowM3) {
                throw new DomainException("Billed water volume {$billedM3} m3 cannot exceed total treated inflow {$inflowM3} m3");
            }

            $lossM3 = $inflowM3 - $billedM3;
            $lossPercentage = $inflowM3 > 0 ? (int) round(($lossM3 / $inflowM3) * 100) : 0;
            $leakAlarmTriggered = $lossPercentage > 15;

            $waterRateMinor = (int) ($params['water_rate_per_m3_minor'] ?? 15000);
            $waterBilledMinor = $billedM3 * $waterRateMinor;

            // District cooling billing
            $coolingThermalKwh = (int) ($params['cooling_thermal_kwh'] ?? 0);
            $coolingRateMinor = (int) ($params['cooling_rate_minor'] ?? 2000);
            $coolingBilledMinor = $coolingThermalKwh * $coolingRateMinor;

            // ESCO split saving calculation
            $verifiedEnergySavingMinor = (int) ($params['verified_energy_saving_minor'] ?? 0);
            $escoSplitPct = (int) ($params['esco_split_pct'] ?? 30);
            $escoFeeMinor = (int) round(($verifiedEnergySavingMinor * $escoSplitPct) / 100);

            $consolidatedUtilityMinor = $waterBilledMinor + $coolingBilledMinor;

            if ($consolidatedUtilityMinor > 0) {
                $idempotencyKey = hash('sha256', "EGY:WATER-COOLING:{$batchCode}:{$consolidatedUtilityMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'ENERGY_CONSOLIDATED_UTILITY',
                    description: "District water and cooling utility billing for {$districtId}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('egy:utility_receivable:IDR', $consolidatedUtilityMinor)
                        ->credit('egy:utility_consolidated_revenue:IDR', $consolidatedUtilityMinor)
                        ->toEntries('IDR'),
                    referenceType: 'EGY_DISTRICT_UTILITY',
                    referenceId: $batchCode,
                ));
            }

            if ($escoFeeMinor > 0) {
                $idempotencyKey = hash('sha256', "EGY:ESCO-SPLIT:{$batchCode}:{$escoFeeMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'ENERGY_ESCO_SAVINGS_SPLIT',
                    description: "ESCO performance contract savings share {$districtId}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('egy:esco_fee_receivable:IDR', $escoFeeMinor)
                        ->credit('egy:esco_service_revenue:IDR', $escoFeeMinor)
                        ->toEntries('IDR'),
                    referenceType: 'EGY_ESCO_SHARE',
                    referenceId: $batchCode,
                ));
            }

            $result = [
                'batch_code' => $batchCode,
                'district_id' => $districtId,
                'water_inflow_m3' => $inflowM3,
                'water_billed_m3' => $billedM3,
                'water_loss_m3' => $lossM3,
                'loss_percentage' => $lossPercentage,
                'leak_alarm_triggered' => $leakAlarmTriggered,
                'water_billed_minor' => $waterBilledMinor,
                'cooling_billed_minor' => $coolingBilledMinor,
                'esco_fee_minor' => $escoFeeMinor,
                'consolidated_total_minor' => $consolidatedUtilityMinor + $escoFeeMinor,
            ];

            $this->audit(
                action: 'egy.water.reconcile_and_billed',
                newValues: [
                    'batch_code' => $batchCode,
                    'loss_m3' => $lossM3,
                    'leak_alarm' => $leakAlarmTriggered ? 'TRIGGERED' : 'CLEAR',
                    'consolidated_minor' => $result['consolidated_total_minor'],
                ],
                correlationId: "egy_water_{$batchCode}",
                impactType: 'water_utility_reconciliation'
            );

            $this->outbox(
                eventType: 'DistrictWaterAndLeakReconciled',
                payload: [
                    'batch_code' => $batchCode,
                    'district_id' => $districtId,
                    'leak_alarm_triggered' => $leakAlarmTriggered,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:WTR:{$batchCode}")
            );

            return $result;
        });
    }
}
