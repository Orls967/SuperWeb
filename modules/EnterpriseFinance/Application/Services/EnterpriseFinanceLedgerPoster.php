<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Support\Posting;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseTaxSummary;
use Modules\EnterpriseFinance\Domain\Models\SyndicatedFacility;
use Modules\EnterpriseFinance\Ledger\EnterpriseFinanceAccounts;
use Modules\EnterpriseFinance\Ledger\EnterpriseFinanceTransactionTypes;
use Psr\Clock\ClockInterface;

class EnterpriseFinanceLedgerPoster
{
    public function __construct(
        protected readonly LedgerService $ledgerService,
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    protected function ensureAccountsSeeded(): void
    {
        if ($this->ledgerService->findAccountByCode('ef:holding_dividend_income:IDR') === null) {
            (new LedgerAccountsSeeder)->run();
        }
    }

    protected function ensureAccount(string $code, string $name, AccountKind $kind, string $assetCode = 'IDR'): void
    {
        if ($this->ledgerService->findAccountByCode($code) === null) {
            EnterpriseFinanceAccounts::ensureAccount($code, $name, $kind, $assetCode, true);
        }
    }

    /**
     * Memosting penutupan periode fiskal (D54-10).
     *
     * Jurnal Berimbang Single-Sign:
     * 1. Penutupan pendapatan: Debit Pendapatan, Kredit Income Summary.
     * 2. Penutupan beban: Debit Income Summary, Kredit Beban.
     * 3. Pemindahan laba/rugi bersih:
     *    - Bila Laba (revenue > expense): Debit Income Summary, Kredit Retained Earnings.
     *    - Bila Rugi (expense > revenue): Debit Retained Earnings, Kredit Income Summary.
     * Total Debit = Total Kredit persis.
     */
    public function postPeriodClosing(
        string $entity,
        string $period,
        int $revenueMinor,
        int $expenseMinor,
        ?string $idempotencyKey = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($revenueMinor < 0 || $expenseMinor < 0) {
            throw new InvalidArgumentException('Nominal pendapatan dan beban penutupan tidak boleh negatif.');
        }

        $currency = 'IDR';
        $incomeSummaryAccount = EnterpriseFinanceAccounts::incomeSummary($entity, $currency);
        $retainedEarningsAccount = EnterpriseFinanceAccounts::retainedEarnings($entity, $currency);
        $revenueNominalAccount = "ef:nominal_revenue:{$entity}:{$currency}";
        $expenseNominalAccount = "ef:nominal_expense:{$entity}:{$currency}";

        $this->ensureAccount($incomeSummaryAccount, "Income Summary {$entity}", AccountKind::LIABILITY, $currency);
        $this->ensureAccount($retainedEarningsAccount, "Retained Earnings {$entity}", AccountKind::LIABILITY, $currency);
        $this->ensureAccount($revenueNominalAccount, "Nominal Revenue Clearing {$entity}", AccountKind::REVENUE, $currency);
        $this->ensureAccount($expenseNominalAccount, "Nominal Expense Clearing {$entity}", AccountKind::EXPENSE, $currency);

        $lines = Posting::lines();

        // 1. Tutup Akun Pendapatan
        if ($revenueMinor > 0) {
            $lines->debit($revenueNominalAccount, $revenueMinor, $currency);
            $lines->credit($incomeSummaryAccount, $revenueMinor, $currency);
        }

        // 2. Tutup Akun Beban
        if ($expenseMinor > 0) {
            $lines->debit($incomeSummaryAccount, $expenseMinor, $currency);
            $lines->credit($expenseNominalAccount, $expenseMinor, $currency);
        }

        // 3. Pindahkan Laba / Rugi Bersih ke Retained Earnings
        $netIncomeMinor = $revenueMinor - $expenseMinor;
        if ($netIncomeMinor > 0) {
            // Laba bersih -> Debit Income Summary, Kredit Retained Earnings
            $lines->debit($incomeSummaryAccount, $netIncomeMinor, $currency);
            $lines->credit($retainedEarningsAccount, $netIncomeMinor, $currency);
        } elseif ($netIncomeMinor < 0) {
            // Rugi bersih -> Debit Retained Earnings, Kredit Income Summary
            $absLossMinor = abs($netIncomeMinor);
            $lines->debit($retainedEarningsAccount, $absLossMinor, $currency);
            $lines->credit($incomeSummaryAccount, $absLossMinor, $currency);
        }

        $idemKey = $idempotencyKey ?? "IDEM:EF:CLOSE:{$entity}:{$period}";

        return $this->ledgerService->post(new PostingDTO(
            type: EnterpriseFinanceTransactionTypes::FISCAL_PERIOD_CLOSING->value,
            description: "Penutupan Buku Periode {$period} Entitas {$entity}",
            idempotencyKey: $idemKey,
            entries: $lines->toEntries($currency),
            referenceType: 'ef_financial_period_locks',
            referenceId: "{$entity}:{$period}",
            meta: [
                'entity' => $entity,
                'period' => $period,
                'revenue_minor' => $revenueMinor,
                'expense_minor' => $expenseMinor,
                'net_income_minor' => $netIncomeMinor,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }

    /**
     * Memosting penyelesaian selisih PPN Masa (D54-10).
     *
     * Jurnal Single-Sign:
     * - Debit PPN Keluaran: vatOutputMinor
     * - Kredit PPN Masukan: vatInputMinor
     * - Kredit Kas Kliring Setoran Negara: (vatOutputMinor - vatInputMinor)
     * Net = 0
     */
    public function postVatSettlement(
        string $entity,
        string $period,
        int $vatOutputMinor,
        int $vatInputMinor,
        ?string $idempotencyKey = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($vatOutputMinor < 0 || $vatInputMinor < 0) {
            throw new InvalidArgumentException('Nominal PPN tidak boleh negatif.');
        }

        $netPayableMinor = $vatOutputMinor - $vatInputMinor;
        if ($netPayableMinor < 0) {
            throw new InvalidArgumentException('PPN Lebih Bayar (restitusi) tidak memerlukan setoran kas.');
        }

        $currency = 'IDR';
        $vatOutputAccount = EnterpriseFinanceAccounts::vatOutputPayable($entity, $currency);
        $vatInputAccount = EnterpriseFinanceAccounts::vatInputReceivable($entity, $currency);
        $vatClearingAccount = EnterpriseFinanceAccounts::vatSettlementClearing($entity, $currency);

        $this->ensureAccount($vatOutputAccount, "VAT Output Payable {$entity}", AccountKind::LIABILITY, $currency);
        $this->ensureAccount($vatInputAccount, "VAT Input Receivable {$entity}", AccountKind::ASSET, $currency);
        $this->ensureAccount($vatClearingAccount, "VAT Settlement Clearing {$entity}", AccountKind::ASSET, $currency);

        $lines = Posting::lines();
        if ($vatOutputMinor > 0) {
            $lines->debit($vatOutputAccount, $vatOutputMinor, $currency);
        }
        if ($vatInputMinor > 0) {
            $lines->credit($vatInputAccount, $vatInputMinor, $currency);
        }
        if ($netPayableMinor > 0) {
            $lines->credit($vatClearingAccount, $netPayableMinor, $currency);
        }

        $idemKey = $idempotencyKey ?? "IDEM:EF:VAT_SETTLE:{$entity}:{$period}";

        return $this->ledgerService->post(new PostingDTO(
            type: EnterpriseFinanceTransactionTypes::TAX_PPN_SETTLEMENT->value,
            description: "Penyelesaian PPN Masa Periode {$period} Entitas {$entity}",
            idempotencyKey: $idemKey,
            entries: $lines->toEntries($currency),
            referenceType: 'ef_tax_recon_summaries',
            referenceId: "{$entity}:{$period}",
            meta: [
                'entity' => $entity,
                'period' => $period,
                'vat_output_minor' => $vatOutputMinor,
                'vat_input_minor' => $vatInputMinor,
                'net_payable_minor' => $netPayableMinor,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }

    /**
     * Memosting dividen sub-holding yang diumumkan (D54-10).
     */
    public function postSubholdingDividend(
        string $entity,
        int $amountMinor,
        ?string $idempotencyKey = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Nominal dividen sub-holding harus lebih besar dari nol.');
        }

        $currency = 'IDR';
        $declaredAccount = EnterpriseFinanceAccounts::dividendDeclared($entity, $currency);
        $payableAccount = EnterpriseFinanceAccounts::dividendPayable($entity, $currency);

        $this->ensureAccount($declaredAccount, "Dividend Declared {$entity}", AccountKind::LIABILITY, $currency);
        $this->ensureAccount($payableAccount, "Dividend Payable {$entity}", AccountKind::LIABILITY, $currency);

        $lines = Posting::lines()
            ->debit($declaredAccount, $amountMinor, $currency)
            ->credit($payableAccount, $amountMinor, $currency);

        $idemKey = $idempotencyKey ?? "IDEM:EF:DIV_DEC:{$entity}:" . time();

        return $this->ledgerService->post(new PostingDTO(
            type: EnterpriseFinanceTransactionTypes::SUBHOLDING_DIVIDEND_DECLARATION->value,
            description: "Deklarasi Dividen Subholding {$entity}",
            idempotencyKey: $idemKey,
            entries: $lines->toEntries($currency),
            referenceType: 'ef_subholding_dividends',
            referenceId: "{$entity}:DIV",
            meta: [
                'entity' => $entity,
                'amount_minor' => $amountMinor,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }

    public function postSyndicatedDrawdown(
        SyndicatedFacility $facility,
        int $amountMinor,
        string $idempotencyKey,
        ?string $referenceNo = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Nominal penarikan fasilitas sindikasi harus lebih besar dari nol.');
        }

        $currency = $facility->currency ?? 'IDR';
        $cashClearing = "ef:cash_disbursement_clearing:{$currency}";
        $loanLiability = "ef:syndicated_loan_liability:{$currency}";

        $this->ensureAccount($cashClearing, "Cash Clearing {$currency}", AccountKind::ASSET, $currency);
        $this->ensureAccount($loanLiability, "Syndicated Loan Liability {$currency}", AccountKind::LIABILITY, $currency);

        $lines = Posting::lines()
            ->debit($cashClearing, $amountMinor, $currency)
            ->credit($loanLiability, $amountMinor, $currency);

        return $this->ledgerService->post(new PostingDTO(
            type: EnterpriseFinanceTransactionTypes::SYNDICATED_FACILITY_DRAWDOWN->value,
            description: "Penarikan Fasilitas Sindikasi {$facility->facility_code} ({$facility->facility_name})",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries($currency),
            referenceType: 'ef_syndicated_facilities',
            referenceId: (string) ($referenceNo ?? $facility->id),
            meta: [
                'facility_code' => $facility->facility_code,
                'drawdown_minor' => $amountMinor,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }

    public function postSyndicatedRepayment(
        SyndicatedFacility $facility,
        int $principalMinor,
        int $interestMinor,
        string $idempotencyKey,
        ?string $referenceNo = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($principalMinor < 0 || $interestMinor < 0 || ($principalMinor + $interestMinor) <= 0) {
            throw new InvalidArgumentException('Total pelunasan pokok dan bunga sindikasi harus positif.');
        }

        $currency = $facility->currency ?? 'IDR';
        $cashClearing = "ef:cash_disbursement_clearing:{$currency}";
        $loanLiability = "ef:syndicated_loan_liability:{$currency}";
        $interestExpense = "ef:syndicated_interest_expense:{$currency}";

        $this->ensureAccount($cashClearing, "Cash Clearing {$currency}", AccountKind::ASSET, $currency);
        $this->ensureAccount($loanLiability, "Syndicated Loan Liability {$currency}", AccountKind::LIABILITY, $currency);
        $this->ensureAccount($interestExpense, "Syndicated Interest Expense {$currency}", AccountKind::EXPENSE, $currency);

        $totalDisbursement = $principalMinor + $interestMinor;
        $lines = Posting::lines();

        if ($principalMinor > 0) {
            $lines->debit($loanLiability, $principalMinor, $currency);
        }
        if ($interestMinor > 0) {
            $lines->debit($interestExpense, $interestMinor, $currency);
        }
        $lines->credit($cashClearing, $totalDisbursement, $currency);

        return $this->ledgerService->post(new PostingDTO(
            type: EnterpriseFinanceTransactionTypes::SYNDICATED_FACILITY_REPAYMENT->value,
            description: "Pelunasan Pokok & Bunga Sindikasi {$facility->facility_code}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries($currency),
            referenceType: 'ef_syndicated_facilities',
            referenceId: (string) ($referenceNo ?? $facility->id),
            meta: [
                'facility_code' => $facility->facility_code,
                'principal_minor' => $principalMinor,
                'interest_minor' => $interestMinor,
                'total_minor' => $totalDisbursement,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }

    public function postBudgetSpendRealization(
        EnterpriseBudget $budget,
        int $amountMinor,
        string $idempotencyKey,
        ?string $referenceNo = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Nominal realisasi belanja harus lebih besar dari nol.');
        }

        $currency = 'IDR';
        $expenseAccount = 'ef:operating_budget_clearing:IDR';
        $cashClearing = 'ef:cash_disbursement_clearing:IDR';

        $this->ensureAccount($expenseAccount, 'Operating Budget Clearing IDR', AccountKind::EXPENSE, $currency);
        $this->ensureAccount($cashClearing, 'Cash Disbursement Clearing IDR', AccountKind::ASSET, $currency);

        $lines = Posting::lines()
            ->debit($expenseAccount, $amountMinor, $currency)
            ->credit($cashClearing, $amountMinor, $currency);

        return $this->ledgerService->post(new PostingDTO(
            type: EnterpriseFinanceTransactionTypes::BUDGET_SPEND_REALIZATION->value,
            description: "Realisasi Belanja Anggaran [{$budget->budget_code}] Cost Center {$budget->cost_center_code}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries($currency),
            referenceType: 'ef_budgets',
            referenceId: (string) ($referenceNo ?? $budget->id),
            meta: [
                'budget_code' => $budget->budget_code,
                'cost_center' => $budget->cost_center_code,
                'amount_minor' => $amountMinor,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }

    public function postTaxSettlement(
        EnterpriseTaxSummary $taxSummary,
        int $payableMinor,
        string $idempotencyKey,
        ?string $referenceNo = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($payableMinor <= 0) {
            throw new InvalidArgumentException('Nominal setoran pajak harus lebih besar dari nol.');
        }

        $currency = 'IDR';
        $taxType = strtoupper($taxSummary->tax_type);
        $taxAccount = ($taxType === 'PPN') ? 'ef:tax_payable_ppn:IDR' : 'ef:tax_payable_pph:IDR';
        $cashClearing = 'ef:cash_disbursement_clearing:IDR';

        $this->ensureAccount($taxAccount, "Tax Payable {$taxType} IDR", AccountKind::LIABILITY, $currency);
        $this->ensureAccount($cashClearing, 'Cash Disbursement Clearing IDR', AccountKind::ASSET, $currency);

        $lines = Posting::lines()
            ->debit($taxAccount, $payableMinor, $currency)
            ->credit($cashClearing, $payableMinor, $currency);

        if ($taxType === 'PPN') {
            return $this->ledgerService->post(new PostingDTO(
                type: EnterpriseFinanceTransactionTypes::TAX_PPN_SETTLEMENT->value,
                description: "Penyetoran Pajak {$taxType} Periode {$taxSummary->period}",
                idempotencyKey: $idempotencyKey,
                entries: $lines->toEntries($currency),
                referenceType: 'ef_tax_summaries',
                referenceId: (string) ($referenceNo ?? $taxSummary->id),
                meta: [
                    'period' => $taxSummary->period,
                    'tax_type' => $taxType,
                    'payable_minor' => $payableMinor,
                ],
                createdBy: $userId,
                postedAt: $this->now(),
            ));
        }

        return $this->ledgerService->post(new PostingDTO(
            type: EnterpriseFinanceTransactionTypes::TAX_PPH_SETTLEMENT->value,
            description: "Penyetoran Pajak {$taxType} Periode {$taxSummary->period}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries($currency),
            referenceType: 'ef_tax_summaries',
            referenceId: (string) ($referenceNo ?? $taxSummary->id),
            meta: [
                'period' => $taxSummary->period,
                'tax_type' => $taxType,
                'payable_minor' => $payableMinor,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }

    public function postDividendDistribution(
        string $subsidiaryCode,
        int $dividendMinor,
        string $idempotencyKey,
        ?string $declarationDate = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($dividendMinor <= 0) {
            throw new InvalidArgumentException('Nominal dividen holding harus lebih besar dari nol.');
        }

        $currency = 'IDR';
        $receivableAccount = 'ef:holding_dividend_receivable:IDR';
        $incomeAccount = 'ef:holding_dividend_income:IDR';

        $this->ensureAccount($receivableAccount, 'Holding Dividend Receivable IDR', AccountKind::ASSET, $currency);
        $this->ensureAccount($incomeAccount, 'Holding Dividend Income IDR', AccountKind::REVENUE, $currency);

        $lines = Posting::lines()
            ->debit($receivableAccount, $dividendMinor, $currency)
            ->credit($incomeAccount, $dividendMinor, $currency);

        return $this->ledgerService->post(new PostingDTO(
            type: EnterpriseFinanceTransactionTypes::HOLDING_DIVIDEND_DECLARATION->value,
            description: "Deklarasi Dividen Anak Perusahaan {$subsidiaryCode} ke Holding",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries($currency),
            referenceType: 'ef_subholding_dividends',
            referenceId: "DIV:{$subsidiaryCode}:{$declarationDate}",
            meta: [
                'subsidiary' => $subsidiaryCode,
                'declaration_date' => $declarationDate,
                'dividend_minor' => $dividendMinor,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }
}
