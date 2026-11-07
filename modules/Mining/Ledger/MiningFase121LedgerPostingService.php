<?php

declare(strict_types=1);

namespace Modules\Mining\Ledger;

use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;

/**
 * Layanan Posting Buku Besar Double-Entry Fase 121 (Smelter, Hilirisasi & Metals Trading Desk).
 * Mengawal 6 kejadian akuntansi operasional dan finansial:
 * 1. Beban Konsumsi Tenaga Listrik Tanur RKEF:
 *    Debit expense:min:smelter_electricity:IDR | Kredit min:smelter_electricity_payable:IDR
 * 2. Kapitalisasi Persediaan Logam NPI/Ferronickel:
 *    Debit min:metals_inventory:IDR | Kredit min:joint_production_cost_clearing:IDR
 * 3. Penyelesaian Mark-to-Market Lindung Nilai Bursa LME (Gain/Loss):
 *    Gain: Debit min:bank_cash:IDR | Kredit min:metals_trading_gain:IDR
 *    Loss: Debit min:metals_trading_loss:IDR | Kredit min:bank_cash:IDR
 * 4. Penarikan Fasilitas Pinjaman SCF Beragun Resi Gudang (SRG):
 *    Debit min:bank_cash:IDR | Kredit min:scf_financing_liability:IDR
 * 5. Pelunasan Fasilitas Pinjaman SCF & Pelepasan Agunan:
 *    Debit min:scf_financing_liability:IDR | Kredit min:bank_cash:IDR
 * 6. Penjualan Produk Sampingan Terak/Slag:
 *    Debit min:byproduct_receivable:IDR | Kredit min:byproduct_sales_revenue:IDR
 */
final class MiningFase121LedgerPostingService
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    /**
     * 1. Pencatatan beban listrik MWh tanur peleburan smelter ke PLN / Captive IPP.
     */
    public function postSmelterElectricityConsumption(
        string $runCode,
        int $powerCostIdr,
        float $powerMwh = 0.0,
        string $description = ''
    ): string {
        $this->assertPositiveAmount($powerCostIdr, 'beban listrik tanur smelter');

        $dto = new PostingDTO(
            type: MiningTransactionTypes::MINING_SMELTER_POWER_EXPENSE->value,
            description: $description ?: "Beban daya listrik {$powerMwh} MWh tanur batch {$runCode}",
            idempotencyKey: "min:smelter-power:{$runCode}",
            entries: Posting::lines()
                ->debit(MiningAccounts::smelterElectricityExpense(), $powerCostIdr, 'IDR')
                ->credit(MiningAccounts::smelterElectricityPayable(), $powerCostIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_SMELTER_RUN',
            referenceId: $runCode,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-PWR-{$runCode}";
    }

    /**
     * 2. Kapitalisasi nilai perolehan produk olahan logam NPI / Ferronickel ke persediaan.
     */
    public function postRefinedMetalsCapitalization(
        string $runCode,
        int $productionCostIdr,
        float $metalTons = 0.0,
        string $description = ''
    ): string {
        $this->assertPositiveAmount($productionCostIdr, 'kapitalisasi produk logam');

        $dto = new PostingDTO(
            type: MiningTransactionTypes::MINING_JOINT_COST_ALLOC->value,
            description: $description ?: "Kapitalisasi persediaan {$metalTons} ton logam NPI batch {$runCode}",
            idempotencyKey: "min:metals-inv:{$runCode}",
            entries: Posting::lines()
                ->debit(MiningAccounts::metalsInventory(), $productionCostIdr, 'IDR')
                ->credit('min:joint_production_cost_clearing:IDR', $productionCostIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_SMELTER_RUN',
            referenceId: $runCode,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-CAP-{$runCode}";
    }

    /**
     * 3. Penyelesaian harian PnL Mark-to-Market posisi lindung nilai LME.
     */
    public function postHedgingMarkToMarketSettlement(
        string $positionCode,
        int $pnlIdr,
        bool $isGain,
        string $description = ''
    ): string {
        $absAmount = abs($pnlIdr);
        $this->assertPositiveAmount($absAmount, 'realisasi MTM hedging LME');

        $lines = Posting::lines();

        if ($isGain) {
            $lines->debit(MiningAccounts::bankCash(), $absAmount, 'IDR')
                ->credit(MiningAccounts::metalsTradingGain(), $absAmount, 'IDR');

            $dto = new PostingDTO(
                type: MiningTransactionTypes::MINING_MTM_GAIN->value,
                description: $description ?: "Settlement harian Mark-to-Market hedging LME {$positionCode}",
                idempotencyKey: "min:hedge-mtm:{$positionCode}",
                entries: $lines->toEntries('IDR'),
                referenceType: 'MINING_METALS_POSITION',
                referenceId: $positionCode,
            );
        } else {
            $lines->debit(MiningAccounts::metalsTradingLoss(), $absAmount, 'IDR')
                ->credit(MiningAccounts::bankCash(), $absAmount, 'IDR');

            $dto = new PostingDTO(
                type: MiningTransactionTypes::MINING_MTM_LOSS->value,
                description: $description ?: "Settlement harian Mark-to-Market hedging LME {$positionCode}",
                idempotencyKey: "min:hedge-mtm:{$positionCode}",
                entries: $lines->toEntries('IDR'),
                referenceType: 'MINING_METALS_POSITION',
                referenceId: $positionCode,
            );
        }

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-MTM-{$positionCode}";
    }

    /**
     * 4. Pencairan pinjaman SCF bank beragun Resi Gudang (SRG).
     */
    public function postWarehouseReceiptScfLoanDrawdown(
        string $receiptNumber,
        int $loanAmountIdr,
        string $bankName,
        string $description = ''
    ): string {
        $this->assertPositiveAmount($loanAmountIdr, 'pencairan SCF resi gudang');

        $dto = new PostingDTO(
            type: MiningTransactionTypes::MINING_SCF_COLLATERAL_DRAW->value,
            description: $description ?: "Pencairan fasilitas SCF {$bankName} beragun resi gudang {$receiptNumber}",
            idempotencyKey: "min:scf-draw:{$receiptNumber}",
            entries: Posting::lines()
                ->debit(MiningAccounts::bankCash(), $loanAmountIdr, 'IDR')
                ->credit(MiningAccounts::scfFinancingLiability(), $loanAmountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_WAREHOUSE_RECEIPT',
            referenceId: $receiptNumber,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-DRW-{$receiptNumber}";
    }

    /**
     * 5. Pelunasan pinjaman SCF bank & pelepasan resi gudang.
     */
    public function postWarehouseReceiptScfLoanRepayment(
        string $receiptNumber,
        int $repayAmountIdr,
        string $bankName,
        string $description = ''
    ): string {
        $this->assertPositiveAmount($repayAmountIdr, 'pelunasan SCF resi gudang');

        $dto = new PostingDTO(
            type: MiningTransactionTypes::MINING_SCF_COLLATERAL_REPAY->value,
            description: $description ?: "Pelunasan penuh pinjaman SCF {$bankName} & pelepasan resi gudang {$receiptNumber}",
            idempotencyKey: "min:scf-repay:{$receiptNumber}",
            entries: Posting::lines()
                ->debit(MiningAccounts::scfFinancingLiability(), $repayAmountIdr, 'IDR')
                ->credit(MiningAccounts::bankCash(), $repayAmountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_WAREHOUSE_RECEIPT',
            referenceId: $receiptNumber,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-RPY-{$receiptNumber}";
    }

    /**
     * 6. Penjualan produk sampingan terak/slag smelter ke offtaker infrastruktur semen.
     */
    public function postByproductSlagSale(
        string $byproductCode,
        int $revenueIdr,
        float $quantityTons = 0.0,
        string $buyerCode = '',
        string $description = ''
    ): string {
        $this->assertPositiveAmount($revenueIdr, 'penjualan terak slag');

        $dto = new PostingDTO(
            type: MiningTransactionTypes::MINING_BYPRODUCT_SLAG_SALE->value,
            description: $description ?: "Penjualan {$quantityTons} ton terak slag batch {$byproductCode} ke {$buyerCode}",
            idempotencyKey: "min:slag-sale:{$byproductCode}",
            entries: Posting::lines()
                ->debit(MiningAccounts::byproductReceivable(), $revenueIdr, 'IDR')
                ->credit(MiningAccounts::byproductSalesRevenue(), $revenueIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'MINING_BYPRODUCT',
            referenceId: $byproductCode,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-SLG-{$byproductCode}";
    }

    /**
     * 7. Alokasi biaya bersama produksi tanur (joint production cost allocation) via M14 NRV.
     */
    public function postJointCostAllocation(
        string $runCode,
        int $totalJointCostIdr,
        int $primaryMetalCostIdr,
        int $byproductsCostIdr,
        string $description = ''
    ): string {
        $this->assertPositiveAmount($totalJointCostIdr, 'alokasi biaya bersama');

        $lines = Posting::lines();
        $lines->debit(MiningAccounts::metalsInventory(), $primaryMetalCostIdr, 'IDR');
        if ($byproductsCostIdr > 0) {
            $lines->debit(MiningAccounts::byproductReceivable(), $byproductsCostIdr, 'IDR');
        }
        $lines->credit(MiningAccounts::jointProductionCostClearing(), $totalJointCostIdr, 'IDR');

        $dto = new PostingDTO(
            type: MiningTransactionTypes::MINING_JOINT_COST_ALLOC->value,
            description: $description ?: "Alokasi biaya bersama smelter RKEF run {$runCode} via NRV",
            idempotencyKey: "min:joint-cost:{$runCode}",
            entries: $lines->toEntries('IDR'),
            referenceType: 'MINING_SMELTER_RUN',
            referenceId: $runCode,
        );

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-JNT-{$runCode}";
    }

    /**
     * 8. Penyesuaian invoice assay akhir (provisional vs final settlement & umpire fee) via M11.
     */
    public function postFinalAssayAdjustment(
        string $lotCode,
        int $differentialIdr,
        int $sellerUmpireCostIdr = 0,
        string $description = ''
    ): string {
        $absDiff = abs($differentialIdr);
        $lines = Posting::lines();

        if ($differentialIdr >= 0) {
            // Buyer pays additional amount to seller
            $lines->debit(MiningAccounts::tradeReceivable(), $absDiff, 'IDR')
                ->credit(MiningAccounts::metalSalesRevenue(), $absDiff, 'IDR');

            $dto = new PostingDTO(
                type: MiningTransactionTypes::MINING_FINAL_ASSAY_ADJ->value,
                description: $description ?: "Penyesuaian akhir assay surplus lot {$lotCode}",
                idempotencyKey: "min:assay-final:{$lotCode}",
                entries: $lines->toEntries('IDR'),
                referenceType: 'MINING_ASSAY_LOT',
                referenceId: $lotCode,
            );
        } else {
            // Seller refunds excess provisional draw
            $lines->debit(MiningAccounts::metalSalesRevenue(), $absDiff, 'IDR')
                ->credit(MiningAccounts::tradeReceivable(), $absDiff, 'IDR');

            $dto = new PostingDTO(
                type: MiningTransactionTypes::MINING_FINAL_ASSAY_ADJ->value,
                description: $description ?: "Penyesuaian akhir assay defisit lot {$lotCode}",
                idempotencyKey: "min:assay-final:{$lotCode}",
                entries: $lines->toEntries('IDR'),
                referenceType: 'MINING_ASSAY_LOT',
                referenceId: $lotCode,
            );
        }

        $journal = $this->ledgerService->post($dto);

        return $journal ? (string) $journal->id : "JRN-ASY-{$lotCode}";
    }

    private function assertPositiveAmount(int $amount, string $label): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Nilai nominal {$label} harus berupa bilangan positif lebih dari 0.");
        }
    }
}
