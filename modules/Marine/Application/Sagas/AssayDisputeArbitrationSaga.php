<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Sagas;

use Exception;
use Modules\Marine\Domain\Algorithms\ExportAssayToleranceChecker;
use Modules\Marine\Domain\Models\AquacultureEscrowSettlement;
use Modules\Marine\Domain\Models\AquacultureExportLot;

/**
 * Saga Terkompensasi: AssayDisputeArbitrationSaga.
 *
 * Mengorkestrasi penanganan sengketa pengujian laboratorium ekspor:
 * Pembekuan Dana Escrow -> Uji Sampel Rujukan Arbitrase -> Putusan Karantina -> Settlement Akhir / Reversal.
 */
class AssayDisputeArbitrationSaga
{
    protected ExportAssayToleranceChecker $toleranceChecker;

    public function __construct(ExportAssayToleranceChecker $toleranceChecker)
    {
        $this->toleranceChecker = $toleranceChecker;
    }

    /**
     * Jalankan arbitrase sengketa hasil uji residu ekspor.
     *
     * @param  array<string, mixed>  $arbitrationLabResults
     * @return array{
     *     dispute_id: string,
     *     lot_tracking_number: string,
     *     verdict: string,
     *     escrow_refunded_minor_cents: int,
     *     escrow_released_to_seller_minor_cents: int,
     *     compensation_steps_count: int,
     *     audit_log: array<string>
     * }
     */
    public function arbitrate(
        string $disputeReference,
        AquacultureExportLot $exportLot,
        AquacultureEscrowSettlement $escrowSettlement,
        string $destinationMarket,
        array $arbitrationLabResults,
        string $referenceLaboratory = 'Eurofins Scientific Reference Lab'
    ): array {
        $reversalLogs = [];
        $compensationSteps = [];

        try {
            // 1. Bekukan status escrow ke DISPUTED
            $previousStatus = $escrowSettlement->status;
            $escrowSettlement->update([
                'status' => 'DISPUTED',
                'notes' => "Dalam penanganan arbitrase laboratorium {$referenceLaboratory} untuk dispute {$disputeReference}.",
            ]);

            $compensationSteps[] = function () use ($escrowSettlement, $previousStatus, &$reversalLogs) {
                $escrowSettlement->update(['status' => $previousStatus]);
                $reversalLogs[] = "Kompensasi: Status escrow dikembalikan ke {$previousStatus}.";
            };

            // 2. Evaluasi hasil uji laboratorium rujukan independen
            $eval = $this->toleranceChecker->evaluateAssay($destinationMarket, $arbitrationLabResults);

            if ($eval['is_eligible_for_export']) {
                // False alarm pada tes pertama: eksportir menang arbitrase, dana dilepaskan
                $escrowSettlement->update([
                    'status' => 'RELEASED',
                    'released_at' => now(),
                    'notes' => "Arbitrase dimenangkan eksportir: Uji rujukan {$referenceLaboratory} PASSED.",
                ]);

                $exportLot->update([
                    'commercial_grade' => 'GRADE_A_ARBITRATED',
                ]);

                return [
                    'dispute_id' => $disputeReference,
                    'lot_tracking_number' => $lotNumber = $exportLot->lot_tracking_number,
                    'verdict' => 'BUYER_CLAIM_DISMISSED_SELLER_PAID',
                    'escrow_refunded_minor_cents' => 0,
                    'escrow_released_to_seller_minor_cents' => (int) $escrowSettlement->net_farmer_payout_minor_cents,
                    'compensation_steps_count' => count($compensationSteps),
                    'audit_log' => ["Uji rujukan {$referenceLaboratory} mengonfirmasi lot {$lotNumber} bebas residu."],
                ];
            } else {
                // Terbukti ada residu terlarang: klaim buyer dikabulkan, refund penuh
                $escrowSettlement->update([
                    'status' => 'REFUNDED',
                    'released_at' => now(),
                    'notes' => "Arbitrase dimenangkan buyer: Terbukti cemaran residu obat oleh {$referenceLaboratory}.",
                ]);

                $exportLot->update([
                    'commercial_grade' => 'REJECT_QUARANTINE_DESTROY',
                ]);

                return [
                    'dispute_id' => $disputeReference,
                    'lot_tracking_number' => $exportLot->lot_tracking_number,
                    'verdict' => 'BUYER_CLAIM_UPHELD_ESCROW_REFUNDED',
                    'escrow_refunded_minor_cents' => (int) $escrowSettlement->net_farmer_payout_minor_cents,
                    'escrow_released_to_seller_minor_cents' => 0,
                    'compensation_steps_count' => count($compensationSteps),
                    'audit_log' => ["Uji rujukan {$referenceLaboratory} mengonfirmasi pelanggaran MRL: ".implode(', ', $eval['violations'])],
                ];
            }
        } catch (Exception $e) {
            foreach (array_reverse($compensationSteps) as $step) {
                try {
                    $step();
                } catch (Exception $ce) {
                    $reversalLogs[] = 'Gagal kompensasi arbitrase: '.$ce->getMessage();
                }
            }

            throw new DomainException('Saga arbitrase sengketa gagal dieksekusi: '.$e->getMessage());
        }
    }
}
