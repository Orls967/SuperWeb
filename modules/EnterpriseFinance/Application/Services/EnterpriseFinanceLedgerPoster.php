<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Banking\Support\Posting;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseTaxSummary;
use Modules\EnterpriseFinance\Domain\Models\SyndicatedFacility;
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

    protected function ensureAccount(string $code, string $name, string $kind, string $assetCode = 'IDR'): void
    {
        if ($this->ledgerService->findAccountByCode($code) === null) {
            LedgerAccount::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'asset_code' => $assetCode,
                    'kind' => $kind,
                    'allow_negative' => true,
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );
        }
    }

    /**
     * Memosting penarikan fasilitas kredit sindikasi korporat (Drawdown).
     *
     * Jurnal Single-Sign:
     * - Debit Kas Clearing: -amountMinor
     * - Kredit Kewajiban Pinjaman Sindikasi: +amountMinor
     * Net = 0
     */
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

        $this->ensureAccount($cashClearing, "Cash Clearing {$currency}", 'asset', $currency);
        $this->ensureAccount($loanLiability, "Syndicated Loan Liability {$currency}", 'liability', $currency);

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

    /**
     * Memosting pelunasan cicilan pokok dan beban bunga pinjaman sindikasi.
     *
     * Jurnal Single-Sign:
     * - Debit Kewajiban Pinjaman Pokok: -principalMinor
     * - Debit Beban Bunga Sindikasi: -interestMinor
     * - Kredit Kas Clearing: +(principalMinor + interestMinor)
     * Net = 0
     */
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

        $this->ensureAccount($cashClearing, "Cash Clearing {$currency}", 'asset', $currency);
        $this->ensureAccount($loanLiability, "Syndicated Loan Liability {$currency}", 'liability', $currency);
        $this->ensureAccount($interestExpense, "Syndicated Interest Expense {$currency}", 'expense', $currency);

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

    /**
     * Memosting realisasi belanja operasional departemen dari pagu anggaran.
     *
     * Jurnal Single-Sign:
     * - Debit Beban Operasional: -amountMinor
     * - Kredit Kas Clearing: +amountMinor
     * Net = 0
     */
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

        $this->ensureAccount($expenseAccount, 'Operating Budget Clearing IDR', 'expense', $currency);
        $this->ensureAccount($cashClearing, 'Cash Disbursement Clearing IDR', 'asset', $currency);

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

    /**
     * Memosting penyetoran kewajiban pajak masa PPN/PPh ke Kas Negara.
     *
     * Jurnal Single-Sign:
     * - Debit Utang Pajak PPN/PPh: -payableMinor
     * - Kredit Kas Clearing: +payableMinor
     * Net = 0
     */
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

        $this->ensureAccount($taxAccount, "Tax Payable {$taxType} IDR", 'liability', $currency);
        $this->ensureAccount($cashClearing, 'Cash Disbursement Clearing IDR', 'asset', $currency);

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

    /**
     * Memosting deklarasi dividen dari anak perusahaan ke Holding Korporat.
     *
     * Jurnal Single-Sign:
     * - Debit Piutang Dividen Holding: -dividendMinor
     * - Kredit Pendapatan Dividen Holding: +dividendMinor
     * Net = 0
     */
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

        $this->ensureAccount($receivableAccount, 'Holding Dividend Receivable IDR', 'asset', $currency);
        $this->ensureAccount($incomeAccount, 'Holding Dividend Income IDR', 'revenue', $currency);

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
