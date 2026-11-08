<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Sagas;

use Exception;
use Modules\Marine\Domain\Algorithms\ExportAssayToleranceChecker;
use Modules\Marine\Domain\Algorithms\SeafoodLotMerkleProofGenerator;
use Modules\Marine\Domain\Models\AquacultureEscrowSettlement;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Modules\Marine\Domain\Models\ExportReeferShipment;
use Modules\Marine\Domain\Models\MarineAquacultureLedgerEntry;
use Modules\Marine\Domain\StateMachines\ExportShipmentStateMachine;

/**
 * Saga Terkompensasi: AquacultureExportExecutionSaga.
 *
 * Mengorkestrasi rantai transaksi ekspor seafood bertaraf ekspor:
 * Validasi Assay -> Lock Escrow Dana Petani -> Alokasi Reefer Container -> Merkle Traceability -> Ledger Entry -> Shipment Dispatch.
 * Jika salah satu langkah gagal, dilakukan kompensasi mundur otomatis (compensating transactions).
 */
class AquacultureExportExecutionSaga
{
    protected ExportAssayToleranceChecker $assayChecker;

    protected SeafoodLotMerkleProofGenerator $merkleGenerator;

    protected ExportShipmentStateMachine $stateMachine;

    public function __construct(
        ExportAssayToleranceChecker $assayChecker,
        SeafoodLotMerkleProofGenerator $merkleGenerator,
        ExportShipmentStateMachine $stateMachine
    ) {
        $this->assayChecker = $assayChecker;
        $this->merkleGenerator = $merkleGenerator;
        $this->stateMachine = $stateMachine;
    }

    /**
     * Jalankan saga ekspor akuakultur.
     *
     * @param  array<string, mixed>  $assayPayload
     * @param  array<int, array<string, mixed>>  $traceEvents
     * @return array{
     *     success: bool,
     *     order_reference: string,
     *     current_step: string,
     *     merkle_root: ?string,
     *     escrow_settlement_id: ?int,
     *     shipment_id: ?int,
     *     reversal_logs: array<string>,
     *     error_message: ?string
     * }
     */
    public function execute(
        string $orderReference,
        AquacultureExportLot $exportLot,
        array $assayPayload,
        array $traceEvents,
        string $destinationMarket,
        int $escrowAmountMinorCents,
        string $reeferContainerNumber
    ): array {
        $compensationSteps = [];
        $reversalLogs = [];
        $merkleRoot = null;
        $escrowSettlement = null;
        $reeferShipment = null;
        $ledgerEntry = null;

        try {
            // Langkah 1: Evaluasi toleransi residu obat & mikrobiologi
            $assayResult = $this->assayChecker->evaluateAssay($destinationMarket, $assayPayload);
            if (! $assayResult['is_eligible_for_export']) {
                throw new Exception('Gagal validasi karantina assay: '.implode('; ', $assayResult['violations']));
            }

            // Langkah 2: Kunci Escrow Pembayaran Petani Tambak
            $escrowSettlement = AquacultureEscrowSettlement::create([
                'escrow_reference' => 'ESC-'.strtoupper(substr(hash('sha256', $orderReference.microtime()), 0, 12)),
                'export_lot_id' => $exportLot->id,
                'beneficiary_farmer_code' => $exportLot->harvestOperation->batch->hatchery->license_number ?? 'FARM-DEFAULT',
                'amount_minor_cents' => $escrowAmountMinorCents,
                'currency' => 'USD',
                'escrow_provider' => 'TRADE_FINANCE_ESCROW_K11',
                'status' => 'LOCKED',
                'payout_condition_checklist' => [
                    'sps_health_certificate' => true,
                    'mass_balance_verified' => true,
                    'vessel_bill_of_lading' => false,
                ],
                'locked_at' => now(),
            ]);

            $compensationSteps[] = function () use ($escrowSettlement, &$reversalLogs) {
                $escrowSettlement->update([
                    'status' => 'CANCELLED',
                    'notes' => 'Dibatalkan oleh kompensasi saga ekspor.',
                ]);
                $reversalLogs[] = "Kompensasi: Escrow {$escrowSettlement->escrow_reference} dibatalkan.";
            };

            // Langkah 3: Alokasikan Kontainer Reefer & Set Status Dingin
            $reeferShipment = ExportReeferShipment::create([
                'shipment_reference' => 'REEF-'.strtoupper(substr(hash('sha256', $orderReference), 0, 10)),
                'order_reference' => $orderReference,
                'export_lot_id' => $exportLot->id,
                'container_number' => $reeferContainerNumber,
                'target_temperature_celsius' => -18.0,
                'current_temperature_celsius' => -18.5,
                'destination_port_code' => $destinationMarket === 'US_FDA' ? 'USLAX' : 'NLRTM',
                'shipment_status' => ExportShipmentStateMachine::STATUS_ESCROW_LOCKED,
                'sps_clearance_status' => 'PASSED',
                'is_cold_chain_breached' => false,
            ]);

            $compensationSteps[] = function () use ($reeferShipment, &$reversalLogs) {
                $reeferShipment->update([
                    'shipment_status' => ExportShipmentStateMachine::STATUS_REJECTED_QUARANTINE,
                    'notes' => 'Alokasi dibatalkan kompensasi saga.',
                ]);
                $reversalLogs[] = "Kompensasi: Kontainer {$reeferShipment->container_number} dibatalkan dari alokasi pengapalan.";
            };

            // Langkah 4: Bangun Merkle Tree Ketelusuran Rantai Pasok
            $merkleTree = $this->merkleGenerator->buildMerkleTree($traceEvents);
            $merkleRoot = $merkleTree['root_hash'];

            $exportLot->update([
                'merkle_root_hash' => $merkleRoot,
                'quality_grade' => $assayResult['health_certificate_grade'],
            ]);

            $compensationSteps[] = function () use ($exportLot, &$reversalLogs) {
                $exportLot->update([
                    'merkle_root_hash' => null,
                ]);
                $reversalLogs[] = "Kompensasi: Merkle Root pada lot {$exportLot->lot_number} dikosongkan kembali.";
            };

            // Langkah 5: Pembukuan Ledger Double-Entry
            $lastEntry = MarineAquacultureLedgerEntry::latest('id')->first();
            $prevHash = $lastEntry ? $lastEntry->entry_hash : str_repeat('0', 64);
            $entryPayload = [
                'order' => $orderReference,
                'lot' => $exportLot->lot_number,
                'escrow' => $escrowAmountMinorCents,
                'root' => $merkleRoot,
            ];
            $currentHash = hash('sha256', $prevHash.json_encode($entryPayload));

            $ledgerEntry = MarineAquacultureLedgerEntry::create([
                'journal_reference' => 'JRN-'.strtoupper(substr(hash('sha256', $orderReference), 0, 10)),
                'account_code' => '1150-AQUACULTURE-INVENTORY',
                'direction' => 'CREDIT',
                'amount_minor_cents' => $escrowAmountMinorCents,
                'currency' => 'USD',
                'lot_reference' => $exportLot->lot_number,
                'previous_entry_hash' => $prevHash,
                'entry_hash' => $currentHash,
                'description' => "Pelepasan ekspor lot {$exportLot->lot_number} ke kontainer {$reeferContainerNumber}",
                'meta' => $entryPayload,
            ]);

            $compensationSteps[] = function () use ($ledgerEntry, &$reversalLogs) {
                $reversalHash = hash('sha256', $ledgerEntry->entry_hash.'REVERSAL');
                MarineAquacultureLedgerEntry::create([
                    'journal_reference' => 'REV-'.$ledgerEntry->journal_reference,
                    'account_code' => $ledgerEntry->account_code,
                    'direction' => 'DEBIT',
                    'amount_minor_cents' => $ledgerEntry->amount_minor_cents,
                    'currency' => $ledgerEntry->currency,
                    'lot_reference' => $ledgerEntry->lot_reference,
                    'previous_entry_hash' => $ledgerEntry->entry_hash,
                    'entry_hash' => $reversalHash,
                    'description' => 'Reversal pembatalan saga ekspor',
                    'meta' => ['reversal_of' => $ledgerEntry->id],
                ]);
                $reversalLogs[] = "Kompensasi: Reversal ledger entry dicatat untuk {$ledgerEntry->journal_reference}.";
            };

            // Langkah 6: Pembaruan status akhir ke REEFER_LOADED / CUSTOMS_CLEARED
            $nextStatus = $this->stateMachine->transition(
                ExportShipmentStateMachine::STATUS_ESCROW_LOCKED,
                ExportShipmentStateMachine::STATUS_INSPECTION_PASSED
            );
            $reeferShipment->update(['shipment_status' => $nextStatus]);

            $finalStatus = $this->stateMachine->transition(
                $nextStatus,
                ExportShipmentStateMachine::STATUS_REEFER_LOADED
            );
            $reeferShipment->update(['shipment_status' => $finalStatus]);

            return [
                'success' => true,
                'order_reference' => $orderReference,
                'current_step' => 'COMPLETED',
                'merkle_root' => $merkleRoot,
                'escrow_settlement_id' => $escrowSettlement->id,
                'shipment_id' => $reeferShipment->id,
                'reversal_logs' => [],
                'error_message' => null,
            ];
        } catch (Exception $e) {
            // Eksekusi kompensasi mundur (backward compensation)
            foreach (array_reverse($compensationSteps) as $step) {
                try {
                    $step();
                } catch (Exception $ce) {
                    $reversalLogs[] = 'Gagal langkah kompensasi: '.$ce->getMessage();
                }
            }

            return [
                'success' => false,
                'order_reference' => $orderReference,
                'current_step' => 'ABORTED_AND_COMPENSATED',
                'merkle_root' => null,
                'escrow_settlement_id' => $escrowSettlement?->id,
                'shipment_id' => $reeferShipment?->id,
                'reversal_logs' => $reversalLogs,
                'error_message' => $e->getMessage(),
            ];
        }
    }
}
