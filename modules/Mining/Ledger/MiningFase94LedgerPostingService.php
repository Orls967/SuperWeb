<?php

declare(strict_types=1);

namespace Modules\Mining\Ledger;

use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;

/**
 * Layanan Pembukuan Buku Besar Operasi Pertambangan Fase 94 (D94-9, U-3).
 *
 * Mengawal 8 kejadian akuntansi kepatuhan minerba, offtake, dan lingkungan:
 * 1. Royalti terutang:
 *    Debit expense:min:royalty:IDR | Kredit min:royalty_payable:IDR
 * 2. Bayar royalti:
 *    Debit min:royalty_payable:IDR | Kredit min:bank_cash:IDR
 * 3. Invoice provisional:
 *    Debit min:trade_receivable:IDR | Kredit min:sales_revenue:IDR
 * 4. Penyesuaian final (assay/kalori):
 *    Selisih antara trade_receivable <-> sales_revenue
 * 5. Susut rekonsiliasi disetujui:
 *    Debit min:inventory_shrinkage_expense:IDR | Kredit min:ore_stockpile_inventory:IDR
 * 6. Unwinding provisi reklamasi:
 *    Debit min:reclamation_expense:IDR | Kredit min:reclamation_provision_liability:IDR
 * 7. Denda lingkungan:
 *    Debit min:environmental_penalty_expense:IDR | Kredit min:environmental_penalty_payable:IDR
 * 8. Selisih kurs settlement:
 *    min:fx_gain_loss:IDR <-> min:trade_receivable:IDR
 */
final class MiningFase94LedgerPostingService
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    /**
     * 1. Akrual royalti minerba terutang atas produksi terverifikasi bulanan.
     */
    public function postRoyaltyAccrual(
        string $returnId,
        string $returnNumber,
        int $amountIdr
    ): string {
        $this->assertPositiveAmount($amountIdr, 'akrual royalti');

        $dto = new PostingDTO(
            type: 'MINING_ROYALTY_ACCRUAL',
            description: "Akrual royalti minerba SPT {$returnNumber}",
            idempotencyKey: "min:royalty-accrual:{$returnId}",
            entries: Posting::lines()
                ->debit(MiningAccounts::royaltyExpense(), $amountIdr, 'IDR')
                ->credit(MiningAccounts::royaltyPayable(), $amountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_ROYALTY_RETURN',
            referenceId: $returnNumber,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-ROYL-{$returnNumber}";
    }

    /**
     * 2. Pelunasan tagihan royalti ke kas negara via bank persepsi / SIMPONI.
     */
    public function postRoyaltyPayment(
        string $returnId,
        string $returnNumber,
        string $paymentRef,
        int $amountIdr
    ): string {
        $this->assertPositiveAmount($amountIdr, 'pembayaran royalti');

        $dto = new PostingDTO(
            type: 'MINING_ROYALTY_PAYMENT',
            description: "Setoran royalti SIMPONI {$returnNumber} NTPN {$paymentRef}",
            idempotencyKey: "min:royalty-payment:{$returnId}",
            entries: Posting::lines()
                ->debit(MiningAccounts::royaltyPayable(), $amountIdr, 'IDR')
                ->credit(MiningAccounts::bankCash(), $amountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_ROYALTY_PAYMENT',
            referenceId: $paymentRef,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-PAY-{$paymentRef}";
    }

    /**
     * 3. Pengakuan piutang dagang dan pendapatan atas invoice provisional pengapalan.
     */
    public function postProvisionalInvoice(
        string $shipmentId,
        string $shipmentNumber,
        int $amountIdr
    ): string {
        $this->assertPositiveAmount($amountIdr, 'invoice provisional');

        $dto = new PostingDTO(
            type: 'MINING_PROVISIONAL_INV',
            description: "Invoice provisional pengapalan {$shipmentNumber}",
            idempotencyKey: "min:provisional-inv:{$shipmentId}",
            entries: Posting::lines()
                ->debit('min:trade_receivable:IDR', $amountIdr, 'IDR')
                ->credit('min:sales_revenue:IDR', $amountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_OFFTAKE_SHIPMENT',
            referenceId: $shipmentNumber,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-PROV-{$shipmentNumber}";
    }

    /**
     * 4. Penyesuaian final nilai invoice berdasarkan assay lab & sertifikat umpire.
     */
    public function postFinalAssayAdjustment(
        string $shipmentId,
        string $shipmentNumber,
        int $adjustmentIdr
    ): string {
        if ($adjustmentIdr === 0) {
            return "JRN-ADJ-ZERO-{$shipmentNumber}";
        }

        $lines = Posting::lines();
        if ($adjustmentIdr > 0) {
            $lines->debit('min:trade_receivable:IDR', $adjustmentIdr, 'IDR')
                ->credit('min:sales_revenue:IDR', $adjustmentIdr, 'IDR');
            $desc = "Penyesuaian premium assay final {$shipmentNumber}";
        } else {
            $absAdj = abs($adjustmentIdr);
            $lines->debit('min:sales_revenue:IDR', $absAdj, 'IDR')
                ->credit('min:trade_receivable:IDR', $absAdj, 'IDR');
            $desc = "Penyesuaian penalti assay final {$shipmentNumber}";
        }

        $dto = new PostingDTO(
            type: 'MINING_FINAL_ASSAY_ADJ',
            description: $desc,
            idempotencyKey: "min:final-assay-adj:{$shipmentId}",
            entries: $lines->toEntries('IDR'),
            referenceType: 'MINING_OFFTAKE_SHIPMENT',
            referenceId: $shipmentNumber,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-FIN-{$shipmentNumber}";
    }

    /**
     * 5. Pembukuan susut persediaan bijih hasil rekonsiliasi tripartit yang disetujui.
     */
    public function postReconciliationShrinkage(
        string $runId,
        string $runNumber,
        int $amountIdr
    ): string {
        $this->assertPositiveAmount($amountIdr, 'susut rekonsiliasi');

        $dto = new PostingDTO(
            type: 'MINING_STOCK_SHRINKAGE',
            description: "Susut persediaan bijih rekonsiliasi {$runNumber}",
            idempotencyKey: "min:stockpile-shrinkage:{$runId}",
            entries: Posting::lines()
                ->debit('min:inventory_shrinkage_expense:IDR', $amountIdr, 'IDR')
                ->credit('min:ore_stockpile_inventory:IDR', $amountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_RECONCILIATION_RUN',
            referenceId: $runNumber,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-SHRINK-{$runNumber}";
    }

    public function postStockShrinkage(
        string $stockpileId,
        string $stockpileCode,
        int $lossAmountIdr
    ): string {
        return $this->postReconciliationShrinkage($stockpileId, $stockpileCode, $lossAmountIdr);
    }

    /**
     * 6. Pembebanan berkala unwinding diskonto liabilitas provisi reklamasi (PSAK).
     */
    public function postReclamationUnwinding(
        string $scheduleId,
        string $projectNumber,
        int $amountIdr
    ): string {
        $this->assertPositiveAmount($amountIdr, 'unwinding provisi reklamasi');

        $dto = new PostingDTO(
            type: 'MINING_RECLAMATION_UNW',
            description: "Unwinding diskonto provisi pascatambang {$projectNumber}",
            idempotencyKey: "min:reclamation-unwind:{$scheduleId}",
            entries: Posting::lines()
                ->debit('min:reclamation_expense:IDR', $amountIdr, 'IDR')
                ->credit('min:reclamation_provision_liability:IDR', $amountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_RECLAMATION_SCHEDULE',
            referenceId: $projectNumber,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-UNWIND-{$projectNumber}";
    }

    /**
     * 7. Akrual kewajiban denda administratif / lingkungan hidup.
     */
    public function postEnvironmentalPenalty(
        string $readingId,
        string $locationCode,
        int $amountIdr
    ): string {
        $this->assertPositiveAmount($amountIdr, 'denda lingkungan');

        $dto = new PostingDTO(
            type: 'MINING_ENV_PENALTY',
            description: "Denda pelanggaran baku mutu lingkungan lokasi {$locationCode}",
            idempotencyKey: "min:env-penalty:{$readingId}",
            entries: Posting::lines()
                ->debit('min:environmental_penalty_expense:IDR', $amountIdr, 'IDR')
                ->credit('min:environmental_penalty_payable:IDR', $amountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_ENV_READING',
            referenceId: $locationCode,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-PENALTY-{$locationCode}";
    }

    /**
     * 8. Pengakuan selisih kurs saat pelunasan settlement offtake ekspor valas.
     */
    public function postFxGainLoss(
        string $shipmentId,
        string $shipmentNumber,
        int $fxDiffIdr
    ): string {
        if ($fxDiffIdr === 0) {
            return "JRN-FX-ZERO-{$shipmentNumber}";
        }

        $lines = Posting::lines();
        if ($fxDiffIdr > 0) {
            // Untung selisih kurs
            $lines->debit('min:trade_receivable:IDR', $fxDiffIdr, 'IDR')
                ->credit('min:fx_gain_loss:IDR', $fxDiffIdr, 'IDR');
            $desc = "Keuntungan selisih kurs settlement {$shipmentNumber}";
        } else {
            // Rugi selisih kurs
            $absLoss = abs($fxDiffIdr);
            $lines->debit('min:fx_gain_loss:IDR', $absLoss, 'IDR')
                ->credit('min:trade_receivable:IDR', $absLoss, 'IDR');
            $desc = "Kerugian selisih kurs settlement {$shipmentNumber}";
        }

        $dto = new PostingDTO(
            type: 'MINING_FX_SETTLEMENT',
            description: $desc,
            idempotencyKey: "min:fx-settlement:{$shipmentId}",
            entries: $lines->toEntries('IDR'),
            referenceType: 'MINING_OFFTAKE_SHIPMENT',
            referenceId: $shipmentNumber,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-FX-{$shipmentNumber}";
    }

    private function assertPositiveAmount(int $amount, string $label): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Nilai nominal {$label} harus lebih besar dari nol, didapat: {$amount}");
        }
    }
}
