<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Intercompany\Application\Actions\AccrueAndPayIntercompanyInterestAction;
use Modules\Intercompany\Application\Actions\AllocateCostSharingAction;
use Modules\Intercompany\Application\Actions\CalculateNciShareAction;
use Modules\Intercompany\Application\Actions\CreateMirroredIntercompanyTransactionAction;
use Modules\Intercompany\Application\Actions\DistributeNciDividendAction;
use Modules\Intercompany\Application\Actions\EliminateUnrealizedInventoryProfitAction;
use Modules\Intercompany\Application\Actions\ExecuteTpTrueUpAdjustmentAction;
use Modules\Intercompany\Application\Actions\GenerateBilateralEliminationJournalAction;
use Modules\Intercompany\Application\Actions\GenerateConsolidatedSegmentReportAction;
use Modules\Intercompany\Application\Actions\GenerateTpDocumentationAction;
use Modules\Intercompany\Application\Actions\IssueIntercompanyLoanAction;
use Modules\Intercompany\Application\Actions\TransferIntercompanyAssetAction;
use Modules\Intercompany\Application\Actions\TransferIntercompanyStockAction;
use Modules\Intercompany\Application\Actions\TranslateSubsidiaryFinancialsAction;
use Modules\Intercompany\Domain\Algorithms\BilateralNettedSettlementMatrixCalculator;
use Modules\Intercompany\Domain\Algorithms\ConsolidationRollupTreeOptimizer;
use Modules\Intercompany\Domain\Algorithms\MultivariateBenchmarkComparator;
use Modules\Intercompany\Domain\Algorithms\NciDividendCascadeCalculator;
use Modules\Intercompany\Domain\Algorithms\TransferPricingMarkupCalculator;
use Modules\Intercompany\Domain\DTOs\ConsolidationTreeResultDTO;
use Modules\Intercompany\Domain\DTOs\MultivariateBenchmarkResultDTO;
use Modules\Intercompany\Domain\DTOs\NciCascadeResultDTO;
use Modules\Intercompany\Domain\DTOs\NettingMatrixResultDTO;
use Modules\Intercompany\Domain\Models\CostSharingAllocation;
use Modules\Intercompany\Domain\Models\EliminationEntry;
use Modules\Intercompany\Domain\Models\IntercompanyAssetTransfer;
use Modules\Intercompany\Domain\Models\IntercompanyLoan;
use Modules\Intercompany\Domain\Models\IntercompanyLoanSchedule;
use Modules\Intercompany\Domain\Models\IntercompanyStockTransfer;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Modules\Intercompany\Domain\Models\NciDividend;
use Modules\Intercompany\Domain\Models\SubsidiaryNci;
use Modules\Intercompany\Domain\Models\TransferPricingDocument;
use Modules\Intercompany\Domain\Models\TransferPricingRule;
use Modules\Intercompany\Domain\Models\TransferPricingTrueUp;
use Modules\Intercompany\Domain\Models\UnrealizedInventoryProfit;
use Psr\Clock\ClockInterface;

class IntercompanyService
{
    public function __construct(
        protected readonly CreateMirroredIntercompanyTransactionAction $createMirrorAction,
        protected readonly IssueIntercompanyLoanAction $issueLoanAction,
        protected readonly AccrueAndPayIntercompanyInterestAction $accrueInterestAction,
        protected readonly AllocateCostSharingAction $allocateCostAction,
        protected readonly GenerateBilateralEliminationJournalAction $eliminationAction,
        protected readonly EliminateUnrealizedInventoryProfitAction $unrealizedProfitAction,
        protected readonly CalculateNciShareAction $nciShareAction,
        protected readonly DistributeNciDividendAction $nciDividendAction,
        protected readonly TranslateSubsidiaryFinancialsAction $translationAction,
        protected readonly GenerateConsolidatedSegmentReportAction $segmentReportAction,
        protected readonly GenerateTpDocumentationAction $tpDocAction,
        protected readonly ExecuteTpTrueUpAdjustmentAction $trueUpAction,
        protected readonly TransferIntercompanyAssetAction $assetTransferAction,
        protected readonly TransferIntercompanyStockAction $stockTransferAction,
        protected readonly TransferPricingMarkupCalculator $tpCalculator,
        protected readonly ?MultivariateBenchmarkComparator $benchmarkComparator = null,
        protected readonly ?BilateralNettedSettlementMatrixCalculator $nettingCalculator = null,
        protected readonly ?ConsolidationRollupTreeOptimizer $rollupOptimizer = null,
        protected readonly ?NciDividendCascadeCalculator $dividendCascadeCalculator = null,
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * 52.1 Transaksi Antar-Entitas Grup (Mirror Transaction SO/PO)
     */
    public function recordMirrorTransaction(
        string $sellingEntity,
        string $buyingEntity,
        string $description,
        int $amountIdr,
        string $currency = 'IDR'
    ): IntercompanyTransaction {
        if ($sellingEntity === $buyingEntity) {
            throw new InvalidArgumentException('Transaksi intercompany tidak boleh antara entitas yang sama: Selling entity and buying entity cannot be the same entity.');
        }

        return $this->createMirrorAction->execute([
            'selling_entity' => $sellingEntity,
            'buying_entity' => $buyingEntity,
            'description' => $description,
            'amount_idr' => $amountIdr,
            'currency' => $currency,
        ]);
    }

    public function issueIntercompanyLoan(
        string $lender,
        string $borrower,
        int $principalIdr,
        float $armsLengthRate,
        string $dueDate
    ): IntercompanyLoan {
        if ($lender === $borrower) {
            throw new InvalidArgumentException('Pinjaman intercompany memerlukan entitas peminjam dan pemberi pinjaman berbeda.');
        }

        $baseRateBps = (int) round($armsLengthRate * 100);

        return $this->issueLoanAction->execute([
            'lender_entity' => $lender,
            'borrower_entity' => $borrower,
            'principal_idr' => $principalIdr,
            'base_rate_bps' => $baseRateBps,
            'spread_bps' => 0,
            'due_date' => $dueDate,
            'start_date' => $this->now()->toDateString(),
            'periods' => 12,
        ]);
    }

    /**
     * 52.2 Transfer Pricing Engine: Validasi Margin Arm's Length
     */
    public function registerTransferPricingRule(
        string $category,
        string $tpMethod,
        float $minMargin,
        float $maxMargin,
        string $benchmarkSource = 'OECD_STAT_2026'
    ): TransferPricingRule {
        $now = $this->now();
        $code = 'TPR-'.$now->format('Ym').'-'.strtoupper(substr(hash('sha256', "{$category}:{$tpMethod}"), 0, 6));

        return TransferPricingRule::create([
            'rule_code' => $code,
            'product_category' => $category,
            'tp_method' => strtoupper($tpMethod),
            'min_arms_length_margin_percent' => $minMargin,
            'max_arms_length_margin_percent' => $maxMargin,
            'min_margin_bps' => (int) round($minMargin * 100),
            'max_margin_bps' => (int) round($maxMargin * 100),
            'median_margin_bps' => (int) round((($minMargin + $maxMargin) / 2) * 100),
            'benchmark_industry_source' => $benchmarkSource,
            'is_active' => true,
        ]);
    }

    public function validateTransferPricingMargin(
        string $category,
        int $costIdr,
        int $intercompanyPriceIdr
    ): array {
        $rule = TransferPricingRule::where('product_category', $category)->where('is_active', true)->first();

        if (! $rule) {
            return [
                'status' => 'UNREGULATED',
                'is_compliant' => true,
                'margin_percent' => 0.0,
            ];
        }

        $marginPercent = (($intercompanyPriceIdr - $costIdr) / $costIdr) * 100.0;
        $min = (float) $rule->min_arms_length_margin_percent;
        $max = (float) $rule->max_arms_length_margin_percent;
        $isCompliant = ($marginPercent >= $min && $marginPercent <= $max);

        return [
            'status' => $isCompliant ? 'COMPLIANT' : 'OUT_OF_RANGE',
            'is_compliant' => $isCompliant,
            'actual_margin_percent' => round($marginPercent, 2),
            'benchmark_range' => "{$min}% - {$max}%",
            'tp_method' => $rule->tp_method,
        ];
    }

    /**
     * 52.4 Mesin Eliminasi Konsolidasi
     */
    public function postEliminationEntry(
        string $period,
        string $eliminationType,
        string $debitAccount,
        string $creditAccount,
        int $amountIdr
    ): EliminationEntry {
        $now = $this->now();
        $code = 'ELIM-'.str_replace('-', '', $period).'-'.strtoupper(substr(hash('sha256', "{$debitAccount}:{$creditAccount}:{$amountIdr}"), 0, 8));

        return EliminationEntry::create([
            'elimination_code' => $code,
            'period' => $period,
            'elimination_type' => $eliminationType,
            'debit_account' => $debitAccount,
            'credit_account' => $creditAccount,
            'amount_idr' => $amountIdr,
            'status' => 'posted',
        ]);
    }

    /**
     * 52.5 Non-Controlling Interest (NCI)
     */
    public function calculateNciShare(
        string $subsidiaryName,
        float $parentSharePercent,
        int $subsidiaryNetIncomeIdr
    ): SubsidiaryNci {
        return $this->nciShareAction->execute(
            subsidiaryName: $subsidiaryName,
            parentSharePercent: $parentSharePercent,
            netIncomeIdr: $subsidiaryNetIncomeIdr,
            period: $this->now()->format('Y-m')
        );
    }

    /**
     * Perluasan 52.4: Eliminasi Bilateral Otomatis D5
     */
    public function generateBilateralElimination(string $period, ?int $operatorId = null): object
    {
        return $this->eliminationAction->execute([
            'period' => $period,
            'operator_id' => $operatorId,
        ]);
    }

    /**
     * Perluasan 52.1: Akrual Bunga Pinjaman D6
     */
    public function accrueLoanInterest(string $loanNumber, int $installmentNumber, ?int $operatorId = null): IntercompanyLoanSchedule
    {
        $loan = IntercompanyLoan::where('loan_agreement_number', $loanNumber)->firstOrFail();

        return $this->accrueInterestAction->execute($loan, $installmentNumber, $operatorId);
    }

    /**
     * Perluasan 52.1: Alokasi Biaya Bersama
     */
    public function allocateCostSharing(
        string $agreementCode,
        string $period,
        string $beneficiaryEntity,
        int $allocationRatioPermille,
        int $baseCostIdr,
        ?int $operatorId = null
    ): CostSharingAllocation {
        return $this->allocateCostAction->execute([
            'agreement_code' => $agreementCode,
            'period' => $period,
            'beneficiary_entity' => $beneficiaryEntity,
            'allocation_ratio_permille' => $allocationRatioPermille,
            'base_cost_idr' => $baseCostIdr,
            'operator_id' => $operatorId,
        ]);
    }

    /**
     * Perluasan 52.3: Mutasi Aset Tetap
     */
    public function transferAsset(array $data): IntercompanyAssetTransfer
    {
        return $this->assetTransferAction->execute($data);
    }

    /**
     * Perluasan 52.3: Pengiriman Stok Lintas Batas
     */
    public function transferStock(array $data): IntercompanyStockTransfer
    {
        return $this->stockTransferAction->execute($data);
    }

    /**
     * Perluasan 52.4: Eliminasi Laba Persediaan Belum Terealisasi
     */
    public function eliminateUnrealizedProfit(array $data): UnrealizedInventoryProfit
    {
        return $this->unrealizedProfitAction->execute($data);
    }

    /**
     * Perluasan 52.4: Translasi Valas Anak FCTR
     */
    public function translateSubsidiary(
        string $subsidiary,
        string $currency,
        int $netAssetsMinor,
        int $netIncomeMinor,
        string $period
    ): array {
        return $this->translationAction->execute($subsidiary, $currency, $netAssetsMinor, $netIncomeMinor, $period);
    }

    /**
     * Perluasan 52.6: Pelaporan Segmen Konsolidasi
     */
    public function generateSegmentReport(string $period): array
    {
        return $this->segmentReportAction->execute($period);
    }

    /**
     * Perluasan 52.2: Generator Dokumentasi TP Local/Master File
     */
    public function generateTpDoc(string $entityCode, string $fiscalYear, string $documentType = 'LOCAL_FILE'): TransferPricingDocument
    {
        return $this->tpDocAction->execute($entityCode, $fiscalYear, $documentType);
    }

    /**
     * Perluasan 52.2: Penyesuaian TP Akhir Tahun
     */
    public function adjustTpTrueUp(string $fiscalYear, string $entityCode, string $category, int $costIdr, int $revenueIdr): TransferPricingTrueUp
    {
        return $this->trueUpAction->execute($fiscalYear, $entityCode, $category, $costIdr, $revenueIdr);
    }

    /**
     * Perluasan 52.5: Pembagian Dividen NCI
     */
    public function distributeNciDividend(string $subsidiary, int $totalDividendIdr, string $declarationDate): NciDividend
    {
        return $this->nciDividendAction->execute($subsidiary, $totalDividendIdr, $declarationDate);
    }

    /**
     * Perluasan 52.2: Evaluasi Pembanding Multivariat Transfer Pricing (OECD / PMK 172)
     */
    public function evaluateMultivariateBenchmark(
        array $config,
        array $testedPartyData,
        array $comparatorsData
    ): MultivariateBenchmarkResultDTO {
        $comparator = $this->benchmarkComparator ?? app(MultivariateBenchmarkComparator::class);

        return $comparator->evaluate($config, $testedPartyData, $comparatorsData);
    }

    /**
     * Perluasan 52.1: Optimasi Multilateral Netting Intercompany Treasury
     */
    public function calculateMultilateralNetting(
        array $config,
        array $grossObligations,
        array $exchangeRatesNumerator = ['IDR' => 100],
        array $exchangeRatesDenominator = ['IDR' => 100]
    ): NettingMatrixResultDTO {
        $calculator = $this->nettingCalculator ?? app(BilateralNettedSettlementMatrixCalculator::class);

        return $calculator->calculate($config, $grossObligations, $exchangeRatesNumerator, $exchangeRatesDenominator);
    }

    /**
     * Perluasan 52.4: Roll-Up Konsolidasi Laporan Keuangan Grup (PSAK 65 / IFRS 10)
     */
    public function executeConsolidationRollup(
        array $config,
        array $trialBalances,
        array $unrealizedInventoryProfits = []
    ): ConsolidationTreeResultDTO {
        $optimizer = $this->rollupOptimizer ?? app(ConsolidationRollupTreeOptimizer::class);

        return $optimizer->rollup($config, $trialBalances, $unrealizedInventoryProfits);
    }

    /**
     * Perluasan 52.5: Cascade Dividen dan Pemotongan Pajak NCI
     */
    public function calculateNciDividendCascade(
        array $declarationConfig,
        array $nciShareholders
    ): NciCascadeResultDTO {
        $calculator = $this->dividendCascadeCalculator ?? app(NciDividendCascadeCalculator::class);

        return $calculator->calculate($declarationConfig, $nciShareholders);
    }

    /**
     * Perluasan 52.2: Ekspor Paket Audit Kepatuhan Transfer Pricing Lengkap (PMK 172)
     */
    public function exportTpAuditPack(array $config, array $financialMetrics, array $comparators): array
    {
        return app(GroupTransferPricingAuditPackExporter::class)->exportAuditPack($config, $financialMetrics, $comparators);
    }

    /**
     * Perluasan 52.4: Orkestrasi Konsolidasi dan Penguncian Periode Eliminasi
     */
    public function runAndLockConsolidation(array $config, array $trialBalances, array $unrealizedProfits = []): array
    {
        return app(ConsolidationEliminationRollupOrchestrator::class)->runAndLock($config, $trialBalances, $unrealizedProfits);
    }

    /**
     * Perluasan 52.3: Resolusi Penilaian dan Eliminasi Pengalihan Aset Tetap Intra-Grup
     */
    public function evaluateAssetTransferValuation(array $data): array
    {
        return app(IntercompanyAssetValuationDisputeResolver::class)->evaluateAssetTransfer($data);
    }

    /**
     * Perluasan 52.1: Eksekusi Penyapuan Saldo Kas Terpusat (Cash Pool Sweeper)
     */
    public function executeCashPoolSweep(array $config, array $participantAccounts): array
    {
        return app(IntercompanyCashPoolSweeper::class)->executeSweep($config, $participantAccounts);
    }

    /**
     * Perluasan 52.4: Pembuatan Jurnal Amortisasi Selisih Nilai Wajar Akuisisi (PPA)
     */
    public function generatePpaAmortization(array $config, array $revaluedAssets): array
    {
        return app(ConsolidationAdjustmentJournalGenerator::class)->generatePpaAmortizationJournals($config, $revaluedAssets);
    }

    /**
     * Perluasan 52.4: Pembuatan Jurnal Metode Ekuitas Asosiasi (PSAK 15)
     */
    public function generateEquityMethodAdjustments(array $data): array
    {
        return app(ConsolidationAdjustmentJournalGenerator::class)->generateEquityMethodJournals($data);
    }

    /**
     * Perluasan 52.4: Eliminasi Transaksi Sewa Antar-Perusahaan (PSAK 73)
     */
    public function generateIntercompanyLeaseEliminations(array $leaseData): array
    {
        return app(ConsolidationAdjustmentJournalGenerator::class)->generateIntercompanyLeaseEliminations($leaseData);
    }

    /**
     * Perluasan 52.4: Penurunan Nilai Goodwill dan Aset CGU (PSAK 48)
     */
    public function generateIntangibleImpairments(array $impairmentData): array
    {
        return app(ConsolidationAdjustmentJournalGenerator::class)->generateIntangibleImpairmentJournals($impairmentData);
    }

    /**
     * 52.7 Audit Grup & Eliminasi (Dual-Source Audit)
     */
    public function auditIntercompany(): array
    {
        $transactions = IntercompanyTransaction::count();
        $loans = IntercompanyLoan::count();
        $rules = TransferPricingRule::count();
        $eliminations = EliminationEntry::count();

        // Invariant 1: tidak boleh transaksi dengan entitas yang sama
        $invalidTx = IntercompanyTransaction::whereColumn('selling_entity', 'buying_entity')->count();

        // Invariant 2: pinjaman repaid <= principal
        $invalidLoans = IntercompanyLoan::whereColumn('repaid_principal_idr', '>', 'principal_idr')->count();

        // Invariant 3: NCI total ownership = 100%
        $invalidNci = SubsidiaryNci::whereRaw('ABS(parent_ownership_percent + nci_ownership_percent - 100) > 0.01')->count();

        $discrepancyCount = $invalidTx + $invalidLoans + $invalidNci;

        return [
            'status' => ($discrepancyCount === 0) ? 'OK' : 'DISCREPANCY',
            'discrepancy_count' => $discrepancyCount,
            'transaction_count' => $transactions,
            'loan_count' => $loans,
            'rule_count' => $rules,
            'elimination_count' => $eliminations,
        ];
    }
}
