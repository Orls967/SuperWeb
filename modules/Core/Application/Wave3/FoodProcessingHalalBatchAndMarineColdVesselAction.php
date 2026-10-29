<?php

declare(strict_types=1);

namespace Modules\Core\Application\Wave3;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class FoodProcessingHalalBatchAndMarineColdVesselAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Enforces CPG agri-food processing mass-balance conservation, SJPH Halal batch segregation,
     * Marine/Aquaculture Total Allowable Catch (TAC) & VMS anti-IUU gates, and cold-vessel excursion quarantine (Fase 168-171).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params): array {
            $batchCode = (string) ($params['batch_code'] ?? 'W3-FOOD-MAR-001');

            // 1. Fase 168-169: CPG Food Processing Mass-Balance Conservation & SJPH Halal Gate
            $rawAgriInputKg = (int) ($params['raw_agri_input_kg'] ?? 10000);
            $finishedFoodKg = (int) ($params['finished_food_kg'] ?? 7800);
            $byproductFeedKg = (int) ($params['byproduct_feed_kg'] ?? 1500);
            $processLossKg = (int) ($params['process_loss_kg'] ?? 700);

            if (($finishedFoodKg + $byproductFeedKg + $processLossKg) > $rawAgriInputKg) {
                throw new DomainException('Food processing mass-balance violation: total output + byproduct + loss exceeds raw input mass');
            }

            $halalCertificateState = (string) ($params['halal_certificate_state'] ?? 'VALID');
            $lineSanitationState = (string) ($params['line_sanitation_state'] ?? 'DEDICATED_HALAL');
            if ($halalCertificateState !== 'VALID' || ! in_array($lineSanitationState, ['DEDICATED_HALAL', 'SERTU_CLEANSED_HALAL'], true)) {
                throw new DomainException("Halal Toyyiban batch gate rejected: cert='{$halalCertificateState}', line='{$lineSanitationState}'");
            }

            // 2. Fase 170-171: Marine TAC Quota, VMS Transponder Anti-IUU & Cold-Vessel Temperature Gate
            $vesselCatchKg = (int) ($params['vessel_catch_kg'] ?? 5000);
            $remainingTacQuotaKg = (int) ($params['remaining_tac_quota_kg'] ?? 8000);
            $vmsTransponderState = (string) ($params['vms_transponder_state'] ?? 'ONLINE_VERIFIED');

            if ($vmsTransponderState !== 'ONLINE_VERIFIED') {
                throw new DomainException("Marine anti-IUU gate blocked catch intake: vessel VMS transponder state is '{$vmsTransponderState}'");
            }

            if ($vesselCatchKg > $remainingTacQuotaKg) {
                throw new DomainException("Marine sustainability violation: vessel catch ({$vesselCatchKg} kg) exceeds remaining TAC quota ({$remainingTacQuotaKg} kg)");
            }

            $maxRecordedTempCentiCelsius = (int) ($params['max_recorded_temp_centi_celsius'] ?? -2000); // -20.00 C
            $maxAllowedTempCentiCelsius = (int) ($params['max_allowed_temp_centi_celsius'] ?? -1800); // -18.00 C
            $exportReleaseRequested = (string) ($params['export_release_action'] ?? 'RELEASE_FOR_EXPORT');

            $coldChainStatus = $maxRecordedTempCentiCelsius <= $maxAllowedTempCentiCelsius
                ? 'COLD_CHAIN_INTACT'
                : 'QUARANTINED_TEMP_EXCURSION';

            if ($coldChainStatus !== 'COLD_CHAIN_INTACT' && $exportReleaseRequested === 'RELEASE_FOR_EXPORT') {
                throw new DomainException("Cold-vessel export blocked: batch {$batchCode} experienced temperature excursion ({$maxRecordedTempCentiCelsius} centi-C > {$maxAllowedTempCentiCelsius} centi-C)");
            }

            $exportUnitPriceMinor = (int) ($params['export_price_per_kg_minor'] ?? 45000);
            $totalBatchRevenueMinor = ($finishedFoodKg + $vesselCatchKg) * $exportUnitPriceMinor;

            $previousHash = (string) ($params['previous_hash'] ?? hash('sha256', 'GENESIS-WAVE3-HALAL-MARINE'));
            $traceabilityHash = hash('sha256', implode('|', [
                $batchCode,
                (string) $finishedFoodKg,
                (string) $vesselCatchKg,
                $coldChainStatus,
                $previousHash,
            ]));

            if ($totalBatchRevenueMinor > 0 && $coldChainStatus === 'COLD_CHAIN_INTACT' && $this->ledger !== null) {
                $idempotencyKey = hash('sha256', "W3:FOODMAR:SETTLE:{$batchCode}:{$totalBatchRevenueMinor}");
                $this->ledger->post(new PostingDTO(
                    type: 'WAVE3_HALAL_FOOD_MARINE',
                    description: "Halal CPG batch & sustainable marine export settlement {$batchCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('wave3:cpg_halal_receivable:IDR', $totalBatchRevenueMinor)
                        ->credit('wave3:cpg_marine_revenue:IDR', $totalBatchRevenueMinor)
                        ->toEntries('IDR'),
                    referenceType: 'WAVE3_FOOD_MARINE_BATCH',
                    referenceId: $batchCode,
                ));
            }

            $result = [
                'batch_code' => $batchCode,
                'yield_efficiency_bps' => $rawAgriInputKg > 0 ? intdiv($finishedFoodKg * 10000, $rawAgriInputKg) : 0,
                'halal_sjph_status' => 'HALAL_BATCH_SEGREGATED',
                'remaining_tac_after_catch_kg' => $remainingTacQuotaKg - $vesselCatchKg,
                'cold_chain_status' => $coldChainStatus,
                'total_batch_revenue_minor' => $totalBatchRevenueMinor,
                'traceability_hash' => $traceabilityHash,
            ];

            $this->audit(
                action: 'core.wave3.food_halal_and_marine_settled',
                newValues: [
                    'batch_code' => $batchCode,
                    'cold_chain_status' => $coldChainStatus,
                    'total_batch_revenue_minor' => $totalBatchRevenueMinor,
                ],
                correlationId: "w3_foodmar_{$batchCode}",
                impactType: 'cpg_halal_marine'
            );

            $this->outbox(
                eventType: 'Wave3HalalFoodAndMarineBatchCertified',
                payload: [
                    'batch_code' => $batchCode,
                    'cold_chain_status' => $coldChainStatus,
                    'traceability_hash' => $traceabilityHash,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:W3:FOODMAR:{$batchCode}")
            );

            return $result;
        });
    }
}
