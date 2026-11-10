<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Services;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Banking\Support\Posting;
use Modules\Intercompany\Ledger\IntercompanyAccounts;
use Modules\Intercompany\Ledger\IntercompanyTransactionTypes;
use Psr\Clock\ClockInterface;

class IntercompanyLedgerPoster
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
        if ($this->ledgerService->findAccountByCode('ic:ar:default:default:IDR') === null) {
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
     * Memosting pasangan transaksi cermin (Mirror Transaction) sisi Penjual dan sisi Pembeli.
     *
     * @return array{seller_tx: LedgerTransaction, buyer_tx: LedgerTransaction}
     */
    public function postMirroredTransaction(
        string $sellingEntity,
        string $buyingEntity,
        int $amountMinor,
        string $currency,
        string $description,
        string $idempotencyKey,
        ?string $referenceNo = null,
        ?int $userId = null
    ): array {
        $this->ensureAccountsSeeded();

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Nominal transaksi mirror harus lebih besar dari nol.');
        }

        $sellerAr = IntercompanyAccounts::ar($buyingEntity, $sellingEntity, $currency);
        $sellerRev = IntercompanyAccounts::sales($sellingEntity, $currency);
        $buyerCogs = IntercompanyAccounts::cogs($sellingEntity, $buyingEntity, $currency);
        $buyerAp = IntercompanyAccounts::ap($sellingEntity, $buyingEntity, $currency);

        $this->ensureAccount($sellerAr, "IC AR {$buyingEntity} from {$sellingEntity}", 'asset', $currency);
        $this->ensureAccount($sellerRev, "IC Sales {$sellingEntity}", 'revenue', $currency);
        $this->ensureAccount($buyerCogs, "IC COGS {$sellingEntity} at {$buyingEntity}", 'expense', $currency);
        $this->ensureAccount($buyerAp, "IC AP {$sellingEntity} at {$buyingEntity}", 'ap', $currency);

        // 1. Buku Penjual: Debit AR (-), Kredit Revenue (+)
        $sellerLines = Posting::lines()
            ->debit($sellerAr, $amountMinor, $currency)
            ->credit($sellerRev, $amountMinor, $currency);

        $sellerTx = $this->ledgerService->post(new PostingDTO(
            type: IntercompanyTransactionTypes::IC_MIRROR_TRANSACTION->value,
            description: "IC Penjualan: {$description} ({$sellingEntity} -> {$buyingEntity})",
            idempotencyKey: "{$idempotencyKey}:SELLER",
            entries: $sellerLines->toEntries($currency),
            referenceType: 'ic_transactions',
            referenceId: $referenceNo,
            meta: [
                'entity' => $sellingEntity,
                'counterparty' => $buyingEntity,
                'direction' => 'SELLER',
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));

        // 2. Buku Pembeli: Debit Beban/Stok (-), Kredit AP (+)
        $buyerLines = Posting::lines()
            ->debit($buyerCogs, $amountMinor, $currency)
            ->credit($buyerAp, $amountMinor, $currency);

        $buyerTx = $this->ledgerService->post(new PostingDTO(
            type: IntercompanyTransactionTypes::IC_MIRROR_TRANSACTION->value,
            description: "IC Pembelian: {$description} ({$buyingEntity} <- {$sellingEntity})",
            idempotencyKey: "{$idempotencyKey}:BUYER",
            entries: $buyerLines->toEntries($currency),
            referenceType: 'ic_transactions',
            referenceId: $referenceNo,
            meta: [
                'entity' => $buyingEntity,
                'counterparty' => $sellingEntity,
                'direction' => 'BUYER',
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));

        return [
            'seller_tx' => $sellerTx,
            'buyer_tx' => $buyerTx,
        ];
    }

    /**
     * Memosting penyelesaian kas (Settlement Clearing) antar-rekening bank entitas.
     */
    public function postSettlementPayment(
        string $payingEntity,
        string $receivingEntity,
        int $amountMinor,
        string $currency,
        string $idempotencyKey,
        ?string $referenceNo = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Nominal penyelesaian settlement harus lebih besar dari nol.');
        }

        $payingAp = IntercompanyAccounts::ap($receivingEntity, $payingEntity, $currency);
        $payingBank = IntercompanyAccounts::bankClearing($payingEntity, $currency);
        $receivingBank = IntercompanyAccounts::bankClearing($receivingEntity, $currency);
        $receivingAr = IntercompanyAccounts::ar($payingEntity, $receivingEntity, $currency);

        $this->ensureAccount($payingAp, "IC AP {$receivingEntity} at {$payingEntity}", 'ap', $currency);
        $this->ensureAccount($payingBank, "Bank Clearing {$payingEntity}", 'clearing', $currency);
        $this->ensureAccount($receivingBank, "Bank Clearing {$receivingEntity}", 'clearing', $currency);
        $this->ensureAccount($receivingAr, "IC AR {$payingEntity} from {$receivingEntity}", 'asset', $currency);

        // Pihak Pembayar: Debit Hutang (-), Kredit Bank (+)
        // Pihak Penerima: Debit Bank (-), Kredit Piutang (+)
        $lines = Posting::lines()
            ->debit($payingAp, $amountMinor, $currency)
            ->credit($payingBank, $amountMinor, $currency)
            ->debit($receivingBank, $amountMinor, $currency)
            ->credit($receivingAr, $amountMinor, $currency);

        return $this->ledgerService->post(new PostingDTO(
            type: IntercompanyTransactionTypes::IC_SETTLEMENT_CLEARING->value,
            description: "IC Settlement: {$payingEntity} membayar {$receivingEntity}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries($currency),
            referenceType: 'ic_transactions',
            referenceId: $referenceNo,
            meta: [
                'payer' => $payingEntity,
                'receiver' => $receivingEntity,
                'amount' => $amountMinor,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }

    /**
     * Memosting pencairan pinjaman antar-entitas (Intercompany Loan Disbursement).
     */
    public function postLoanDisbursement(
        string $lenderEntity,
        string $borrowerEntity,
        int $principalMinor,
        string $currency,
        string $loanNumber,
        string $idempotencyKey,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($principalMinor <= 0) {
            throw new InvalidArgumentException('Pokok pinjaman harus lebih besar dari nol.');
        }

        $lenderLoanRec = IntercompanyAccounts::loanReceivable($borrowerEntity, $lenderEntity, $currency);
        $lenderBank = IntercompanyAccounts::bankClearing($lenderEntity, $currency);
        $borrowerBank = IntercompanyAccounts::bankClearing($borrowerEntity, $currency);
        $borrowerLoanPay = IntercompanyAccounts::loanPayable($lenderEntity, $borrowerEntity, $currency);

        $this->ensureAccount($lenderLoanRec, "IC Loan Rec {$borrowerEntity} from {$lenderEntity}", 'loan_receivable', $currency);
        $this->ensureAccount($lenderBank, "Bank Clearing {$lenderEntity}", 'clearing', $currency);
        $this->ensureAccount($borrowerBank, "Bank Clearing {$borrowerEntity}", 'clearing', $currency);
        $this->ensureAccount($borrowerLoanPay, "IC Loan Pay {$lenderEntity} at {$borrowerEntity}", 'liability', $currency);

        // Lender: Debit Piutang Pinjaman (-), Kredit Kas Bank (+)
        // Borrower: Debit Kas Bank (-), Kredit Hutang Pinjaman (+)
        $lines = Posting::lines()
            ->debit($lenderLoanRec, $principalMinor, $currency)
            ->credit($lenderBank, $principalMinor, $currency)
            ->debit($borrowerBank, $principalMinor, $currency)
            ->credit($borrowerLoanPay, $principalMinor, $currency);

        return $this->ledgerService->post(new PostingDTO(
            type: IntercompanyTransactionTypes::IC_LOAN_DISBURSEMENT->value,
            description: "Pencairan pinjaman intercompany {$loanNumber} ({$lenderEntity} -> {$borrowerEntity})",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries($currency),
            referenceType: 'ic_loans',
            referenceId: $loanNumber,
            meta: [
                'lender' => $lenderEntity,
                'borrower' => $borrowerEntity,
                'principal' => $principalMinor,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }

    /**
     * Memosting akrual bunga pinjaman & pemotongan pajak PPh 23 / 26.
     */
    public function postLoanInterestAccrual(
        string $lenderEntity,
        string $borrowerEntity,
        int $interestGrossMinor,
        int $taxWithheldMinor,
        string $currency,
        string $loanNumber,
        int $installmentNumber,
        string $idempotencyKey,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($interestGrossMinor <= 0) {
            throw new InvalidArgumentException('Bunga kotor harus lebih besar dari nol.');
        }

        $netInterestMinor = $interestGrossMinor - $taxWithheldMinor;

        $borrowerExp = IntercompanyAccounts::interestExpense($lenderEntity, $borrowerEntity, $currency);
        $borrowerPay = IntercompanyAccounts::interestPayable($lenderEntity, $borrowerEntity, $currency);
        $borrowerWht = IntercompanyAccounts::withholdingTaxPayable($borrowerEntity, $currency);

        $lenderRec = IntercompanyAccounts::interestReceivable($borrowerEntity, $lenderEntity, $currency);
        $lenderPrepaid = IntercompanyAccounts::prepaidTaxPph23($lenderEntity, $currency);
        $lenderInc = IntercompanyAccounts::interestIncome($borrowerEntity, $lenderEntity, $currency);

        $this->ensureAccount($borrowerExp, "IC Interest Exp to {$lenderEntity}", 'expense', $currency);
        $this->ensureAccount($borrowerPay, "IC Interest Pay to {$lenderEntity}", 'liability', $currency);
        $this->ensureAccount($borrowerWht, "WHT Payable {$borrowerEntity}", 'liability', $currency);
        $this->ensureAccount($lenderRec, "IC Interest Rec from {$borrowerEntity}", 'asset', $currency);
        $this->ensureAccount($lenderPrepaid, "Prepaid Tax PPh 23 {$lenderEntity}", 'asset', $currency);
        $this->ensureAccount($lenderInc, "IC Interest Inc from {$borrowerEntity}", 'revenue', $currency);

        // Sisi Peminjam: Debit Beban (-100%), Kredit Hutang (+85%), Kredit Pajak Terutang (+15%)
        // Sisi Pemberi: Debit Piutang Bunga (-85%), Debit Uang Muka PPh 23 (-15%), Kredit Pendapatan (+100%)
        $lines = Posting::lines()
            ->debit($borrowerExp, $interestGrossMinor, $currency)
            ->credit($borrowerPay, $netInterestMinor, $currency)
            ->credit($borrowerWht, $taxWithheldMinor, $currency)
            ->debit($lenderRec, $netInterestMinor, $currency)
            ->debit($lenderPrepaid, $taxWithheldMinor, $currency)
            ->credit($lenderInc, $interestGrossMinor, $currency);

        return $this->ledgerService->post(new PostingDTO(
            type: IntercompanyTransactionTypes::IC_LOAN_INTEREST_ACCRUAL->value,
            description: "Akrual bunga pinjaman {$loanNumber} cicilan ke-{$installmentNumber} ({$borrowerEntity} -> {$lenderEntity})",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries($currency),
            referenceType: 'ic_loans',
            referenceId: $loanNumber,
            meta: [
                'installment_number' => $installmentNumber,
                'interest_gross' => $interestGrossMinor,
                'tax_withheld' => $taxWithheldMinor,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }

    /**
     * Memosting jurnal alokasi biaya bersama (Cost Sharing / Management Fee).
     */
    public function postCostSharingBilling(
        string $providerEntity,
        string $beneficiaryEntity,
        int $totalBilledIdr,
        string $agreementCode,
        string $period,
        string $idempotencyKey,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($totalBilledIdr <= 0) {
            throw new InvalidArgumentException('Total tagihan pembagian biaya harus lebih besar dari nol.');
        }

        $beneficiaryExp = IntercompanyAccounts::managementFeeExpense($providerEntity, $beneficiaryEntity, 'IDR');
        $providerRecov = IntercompanyAccounts::costSharingRecovery($providerEntity, $beneficiaryEntity, 'IDR');

        $this->ensureAccount($beneficiaryExp, "Management Fee to {$providerEntity}", 'expense', 'IDR');
        $this->ensureAccount($providerRecov, "Cost Recovery from {$beneficiaryEntity}", 'revenue', 'IDR');

        $lines = Posting::lines()
            ->debit($beneficiaryExp, $totalBilledIdr, 'IDR')
            ->credit($providerRecov, $totalBilledIdr, 'IDR');

        return $this->ledgerService->post(new PostingDTO(
            type: IntercompanyTransactionTypes::IC_COST_SHARING_BILLING->value,
            description: "Alokasi biaya bersama {$agreementCode} periode {$period} ({$providerEntity} -> {$beneficiaryEntity})",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'ic_cost_sharing_agreements',
            referenceId: $agreementCode,
            meta: [
                'period' => $period,
                'provider' => $providerEntity,
                'beneficiary' => $beneficiaryEntity,
                'amount' => $totalBilledIdr,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }

    /**
     * Memosting jurnal eliminasi bilateral konsolidasi (AR/AP dan Sales/COGS).
     */
    public function postBilateralElimination(
        string $sellingEntity,
        string $buyingEntity,
        int $eliminatedBalanceIdr,
        int $eliminatedPnlIdr,
        string $period,
        string $eliminationCode,
        string $idempotencyKey,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        $lines = Posting::lines();

        if ($eliminatedBalanceIdr > 0) {
            $apAccount = IntercompanyAccounts::ap($sellingEntity, $buyingEntity, 'IDR');
            $arAccount = IntercompanyAccounts::ar($buyingEntity, $sellingEntity, 'IDR');
            $this->ensureAccount($apAccount, "IC AP {$sellingEntity} at {$buyingEntity}", 'ap', 'IDR');
            $this->ensureAccount($arAccount, "IC AR {$buyingEntity} from {$sellingEntity}", 'asset', 'IDR');
            $lines->debit($apAccount, $eliminatedBalanceIdr, 'IDR')
                ->credit($arAccount, $eliminatedBalanceIdr, 'IDR');
        }

        if ($eliminatedPnlIdr > 0) {
            $salesAccount = IntercompanyAccounts::sales($sellingEntity, 'IDR');
            $cogsAccount = IntercompanyAccounts::cogs($sellingEntity, $buyingEntity, 'IDR');
            $this->ensureAccount($salesAccount, "IC Sales {$sellingEntity}", 'revenue', 'IDR');
            $this->ensureAccount($cogsAccount, "IC COGS {$sellingEntity} at {$buyingEntity}", 'expense', 'IDR');
            $lines->debit($salesAccount, $eliminatedPnlIdr, 'IDR')
                ->credit($cogsAccount, $eliminatedPnlIdr, 'IDR');
        }

        return $this->ledgerService->post(new PostingDTO(
            type: IntercompanyTransactionTypes::IC_BILATERAL_ELIMINATION->value,
            description: "Jurnal eliminasi konsolidasi {$eliminationCode} periode {$period} ({$sellingEntity} & {$buyingEntity})",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'ic_elimination_entries',
            referenceId: $eliminationCode,
            meta: [
                'period' => $period,
                'seller' => $sellingEntity,
                'buyer' => $buyingEntity,
                'eliminated_balance' => $eliminatedBalanceIdr,
                'eliminated_pnl' => $eliminatedPnlIdr,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }

    /**
     * Memosting eliminasi laba persediaan belum terealisasi.
     */
    public function postUnrealizedInventoryProfitElimination(
        string $sellingEntity,
        string $buyingEntity,
        int $unrealizedProfitIdr,
        string $period,
        string $idempotencyKey,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($unrealizedProfitIdr <= 0) {
            throw new InvalidArgumentException('Nominal laba persediaan belum terealisasi harus lebih besar dari nol.');
        }

        $reserveAccount = IntercompanyAccounts::unrealizedProfitReserve($sellingEntity, 'IDR');
        $inventoryAdjAccount = IntercompanyAccounts::inventoryAdjustment($buyingEntity, 'IDR');

        $this->ensureAccount($reserveAccount, "Unrealized Profit Reserve {$sellingEntity}", 'liability', 'IDR');
        $this->ensureAccount($inventoryAdjAccount, "Inventory Adj {$buyingEntity}", 'expense', 'IDR');

        $lines = Posting::lines()
            ->debit($reserveAccount, $unrealizedProfitIdr, 'IDR')
            ->credit($inventoryAdjAccount, $unrealizedProfitIdr, 'IDR');

        return $this->ledgerService->post(new PostingDTO(
            type: IntercompanyTransactionTypes::IC_UNREALIZED_PROFIT_ELIM->value,
            description: "Eliminasi laba persediaan intra-grup periode {$period} ({$sellingEntity} -> {$buyingEntity})",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'ic_unrealized_inventory_profits',
            referenceId: "UIP:{$period}:{$sellingEntity}:{$buyingEntity}",
            meta: [
                'period' => $period,
                'unrealized_profit' => $unrealizedProfitIdr,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }

    /**
     * Memosting selisih kurs translasi laporan valas anak (FCTR).
     */
    public function postFctrTranslation(
        string $subsidiaryEntity,
        int $translationDifferenceIdr,
        string $period,
        string $idempotencyKey,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($translationDifferenceIdr === 0) {
            throw new InvalidArgumentException('Tidak ada selisih translasi valas untuk diposting.');
        }

        $fctrAccount = IntercompanyAccounts::fctrReserve($subsidiaryEntity, 'IDR');
        $equityAccount = IntercompanyAccounts::foreignEquityAdj($subsidiaryEntity, 'IDR');

        $this->ensureAccount($fctrAccount, "FCTR Reserve {$subsidiaryEntity}", 'liability', 'IDR');
        $this->ensureAccount($equityAccount, "Foreign Equity Adj {$subsidiaryEntity}", 'liability', 'IDR');

        $lines = Posting::lines();
        if ($translationDifferenceIdr > 0) {
            // Untung translasi: Debit Ekuitas Anak, Kredit Cadangan FCTR
            $lines->debit($equityAccount, $translationDifferenceIdr, 'IDR')
                ->credit($fctrAccount, $translationDifferenceIdr, 'IDR');
        } else {
            // Rugi translasi: Debit Cadangan FCTR, Kredit Ekuitas Anak
            $absDiff = abs($translationDifferenceIdr);
            $lines->debit($fctrAccount, $absDiff, 'IDR')
                ->credit($equityAccount, $absDiff, 'IDR');
        }

        return $this->ledgerService->post(new PostingDTO(
            type: IntercompanyTransactionTypes::IC_FCTR_TRANSLATION_ADJ->value,
            description: "Penyesuaian selisih kurs translasi FCTR {$subsidiaryEntity} periode {$period}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'ic_entities',
            referenceId: $subsidiaryEntity,
            meta: [
                'period' => $period,
                'difference' => $translationDifferenceIdr,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }

    /**
     * Memosting bagian laba kepentingan non-pengendali (NCI).
     */
    public function postNciProfitShare(
        string $subsidiaryEntity,
        int $nciShareNetIncomeIdr,
        string $period,
        string $idempotencyKey,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($nciShareNetIncomeIdr <= 0) {
            throw new InvalidArgumentException('Bagian laba NCI harus lebih besar dari nol.');
        }

        $shareExpense = IntercompanyAccounts::nciProfitShare($subsidiaryEntity, 'IDR');
        $nciEquity = IntercompanyAccounts::nciEquity($subsidiaryEntity, 'IDR');

        $this->ensureAccount($shareExpense, "NCI Profit Share {$subsidiaryEntity}", 'expense', 'IDR');
        $this->ensureAccount($nciEquity, "NCI Equity {$subsidiaryEntity}", 'liability', 'IDR');

        $lines = Posting::lines()
            ->debit($shareExpense, $nciShareNetIncomeIdr, 'IDR')
            ->credit($nciEquity, $nciShareNetIncomeIdr, 'IDR');

        return $this->ledgerService->post(new PostingDTO(
            type: IntercompanyTransactionTypes::IC_NCI_PROFIT_ALLOCATION->value,
            description: "Alokasi bagian laba non-pengendali (NCI) {$subsidiaryEntity} periode {$period}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'ic_subsidiary_nci',
            referenceId: "NCI:{$period}:{$subsidiaryEntity}",
            meta: [
                'period' => $period,
                'nci_share' => $nciShareNetIncomeIdr,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }

    /**
     * Memosting pembagian dividen ke pemegang saham non-pengendali.
     */
    public function postNciDividendPayment(
        string $subsidiaryEntity,
        int $nciDividendIdr,
        string $idempotencyKey,
        ?string $declarationDate = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($nciDividendIdr <= 0) {
            throw new InvalidArgumentException('Nominal dividen NCI harus lebih besar dari nol.');
        }

        $nciEquity = IntercompanyAccounts::nciEquity($subsidiaryEntity, 'IDR');
        $bankClearing = IntercompanyAccounts::bankClearing($subsidiaryEntity, 'IDR');

        $this->ensureAccount($nciEquity, "NCI Equity {$subsidiaryEntity}", 'liability', 'IDR');
        $this->ensureAccount($bankClearing, "Bank Clearing {$subsidiaryEntity}", 'clearing', 'IDR');

        $lines = Posting::lines()
            ->debit($nciEquity, $nciDividendIdr, 'IDR')
            ->credit($bankClearing, $nciDividendIdr, 'IDR');

        return $this->ledgerService->post(new PostingDTO(
            type: IntercompanyTransactionTypes::IC_NCI_DIVIDEND_PAYOUT->value,
            description: "Pembayaran dividen kas NCI {$subsidiaryEntity}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'ic_nci_dividends',
            referenceId: "DIV:{$subsidiaryEntity}:{$declarationDate}",
            meta: [
                'nci_dividend' => $nciDividendIdr,
                'declaration_date' => $declarationDate,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        ));
    }
}
