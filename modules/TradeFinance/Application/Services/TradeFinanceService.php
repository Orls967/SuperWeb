<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Application\Services;

use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\TradeFinance\Domain\Models\BankGuarantee;
use Modules\TradeFinance\Domain\Models\DocumentaryCollection;
use Modules\TradeFinance\Domain\Models\LcDocument;
use Modules\TradeFinance\Domain\Models\LetterOfCredit;
use Modules\TradeFinance\Domain\Models\TradeLoan;
use Modules\Treasury\Application\Services\TreasuryService;

class TradeFinanceService
{
    public function __construct(
        protected ?Ledger $ledger = null,
        protected ?TreasuryService $treasuryService = null
    ) {
        $this->ledger = $ledger ?? app(Ledger::class);
        $this->treasuryService = $treasuryService ?? app(TreasuryService::class);
    }

    /**
     * 50.1 Letter of Credit (UCP 600 simulasi): Penerbitan & Status Lifecycle
     */
    public function issueLetterOfCredit(
        string $applicantName,
        string $beneficiaryName,
        string $issuingBank,
        string $advisingBank,
        int $foreignAmount,
        string $currency,
        string $expiryDate,
        string $type = 'sight',
        int $tenorDays = 0
    ): LetterOfCredit {
        $functionalIdr = $this->treasuryService->convertAmount($foreignAmount, $currency, 'IDR');

        return DB::transaction(function () use (
            $applicantName,
            $beneficiaryName,
            $issuingBank,
            $advisingBank,
            $foreignAmount,
            $currency,
            $expiryDate,
            $type,
            $tenorDays,
            $functionalIdr
        ) {
            $lc = LetterOfCredit::create([
                'lc_number' => 'LC-'.strtoupper(Str::random(10)),
                'type' => $type,
                'issuing_bank' => $issuingBank,
                'advising_bank' => $advisingBank,
                'applicant_name' => $applicantName,
                'beneficiary_name' => $beneficiaryName,
                'currency' => strtoupper($currency),
                'amount_foreign' => $foreignAmount,
                'amount_functional_idr' => $functionalIdr,
                'issue_date' => Carbon::today()->toDateString(),
                'expiry_date' => $expiryDate,
                'tenor_days' => $tenorDays,
                'status' => 'issued',
            ]);

            // 50.7 Jurnal memorandum kewajiban kontinjensi L/C di ledger
            LedgerAccount::firstOrCreate(
                ['code' => 'tf:contingent_lc:IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'asset_code' => 'IDR',
                    'kind' => 'ASSET',
                    'name' => 'Trade Finance Contingent LC Exposure IDR',
                    'allow_negative' => true,
                    'cached_balance' => '0',
                ]
            );

            LedgerAccount::firstOrCreate(
                ['code' => 'tf:contra_lc:IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'asset_code' => 'IDR',
                    'kind' => 'LIABILITY',
                    'name' => 'Trade Finance Contra LC IDR',
                    'allow_negative' => true,
                    'cached_balance' => '0',
                ]
            );

            $this->ledger->post(new PostingDTO(
                type: 'TF_LC_ISSUED',
                description: "LC {$lc->lc_number} contingent exposure booked",
                idempotencyKey: 'LC_EXP_'.$lc->lc_number,
                entries: [
                    PostingEntryDTO::forCode(
                        accountCode: 'tf:contingent_lc:IDR',
                        assetCode: 'IDR',
                        amount: BigDecimal::of((string) (-$functionalIdr))
                    ),
                    PostingEntryDTO::forCode(
                        accountCode: 'tf:contra_lc:IDR',
                        assetCode: 'IDR',
                        amount: BigDecimal::of((string) $functionalIdr)
                    ),
                ]
            ));

            return $lc;
        });
    }

    /**
     * 50.2 Presentasi dokumen & deteksi diskrepansi
     */
    public function presentDocument(
        LetterOfCredit $lc,
        string $docName,
        string $docNumber,
        bool $hasDiscrepancy = false,
        ?string $details = null
    ): LcDocument {
        $doc = LcDocument::create([
            'letter_of_credit_id' => $lc->id,
            'doc_name' => $docName,
            'document_number' => $docNumber,
            'has_discrepancy' => $hasDiscrepancy,
            'discrepancy_details' => $details,
            'is_waived_by_applicant' => false,
        ]);

        if ($hasDiscrepancy) {
            $lc->update(['status' => 'discrepancies_found']);
        } else {
            $lc->update(['status' => 'presented']);
        }

        return $doc;
    }

    public function waiveDiscrepancy(LcDocument $doc): LcDocument
    {
        $doc->update(['is_waived_by_applicant' => true]);

        // Cek jika seluruh dokumen sudah bebas diskrepansi / sudah di-waive
        $hasPendingDiscrepancies = LcDocument::where('letter_of_credit_id', $doc->letter_of_credit_id)
            ->where('has_discrepancy', true)
            ->where('is_waived_by_applicant', false)
            ->exists();

        if (! $hasPendingDiscrepancies) {
            $doc->letterOfCredit->update(['status' => 'accepted']);
        }

        return $doc;
    }

    /**
     * 50.3 Documentary Collection (D/P, D/A)
     */
    public function createCollection(
        string $type,
        string $drawer,
        string $drawee,
        string $collectingBank,
        int $amountForeign,
        string $currency = 'USD',
        int $tenorDays = 0
    ): DocumentaryCollection {
        return DocumentaryCollection::create([
            'collection_number' => 'COL-'.strtoupper(Str::random(8)),
            'type' => strtoupper($type),
            'drawer_name' => $drawer,
            'drawee_name' => $drawee,
            'collecting_bank' => $collectingBank,
            'currency' => strtoupper($currency),
            'amount_foreign' => $amountForeign,
            'tenor_days' => $tenorDays,
            'status' => 'presented',
        ]);
    }

    public function payCollection(DocumentaryCollection $col): DocumentaryCollection
    {
        $col->update(['status' => 'paid']);

        return $col;
    }

    /**
     * 50.4 Garansi Bank (Bank Guarantee: Bid Bond, Performance Bond, Advance Payment)
     */
    public function issueBankGuarantee(
        string $type,
        string $issuingBank,
        string $applicant,
        string $beneficiary,
        int $amountIdr,
        string $effectiveDate,
        string $expiryDate
    ): BankGuarantee {
        return BankGuarantee::create([
            'guarantee_number' => 'BG-'.strtoupper(Str::random(8)),
            'type' => $type,
            'issuing_bank' => $issuingBank,
            'applicant_name' => $applicant,
            'beneficiary_name' => $beneficiary,
            'amount_idr' => $amountIdr,
            'effective_date' => $effectiveDate,
            'expiry_date' => $expiryDate,
            'claim_amount_idr' => 0,
            'status' => 'active',
        ]);
    }

    public function claimBankGuarantee(BankGuarantee $bg, int $claimAmount): BankGuarantee
    {
        if ($claimAmount > $bg->amount_idr) {
            throw new InvalidArgumentException('Klaim garansi bank melebihi nilai plafon garansi.');
        }

        $bg->update([
            'claim_amount_idr' => $claimAmount,
            'status' => 'claimed',
        ]);

        return $bg;
    }

    /**
     * 50.5 Pembiayaan Perdagangan (Pre/Post-shipment financing & Factoring)
     */
    public function disburseTradeLoan(
        string $facilityType,
        string $borrower,
        int $principalIdr,
        float $interestPercent,
        string $dueDate
    ): TradeLoan {
        return TradeLoan::create([
            'loan_number' => 'TL-'.strtoupper(Str::random(8)),
            'facility_type' => $facilityType,
            'borrower_name' => $borrower,
            'principal_amount_idr' => $principalIdr,
            'interest_rate_percent' => $interestPercent,
            'disbursed_at' => Carbon::today()->toDateString(),
            'due_date' => $dueDate,
            'repaid_amount_idr' => 0,
            'status' => 'disbursed',
        ]);
    }

    public function repayTradeLoan(TradeLoan $loan, int $repayAmountIdr): TradeLoan
    {
        $newRepaid = $loan->repaid_amount_idr + $repayAmountIdr;
        $status = ($newRepaid >= $loan->principal_amount_idr) ? 'settled' : 'partially_repaid';

        $loan->update([
            'repaid_amount_idr' => $newRepaid,
            'status' => $status,
        ]);

        return $loan;
    }

    /**
     * 50.8 Audit Trade Finance: Validasi eksposur L/C, garansi bank & loan
     */
    public function auditTradeFinance(): array
    {
        $lcs = LetterOfCredit::count();
        $collections = DocumentaryCollection::count();
        $guarantees = BankGuarantee::count();
        $loans = TradeLoan::count();

        // Validasi invariant: tidak ada klaim garansi yang melebihi nilai plafon
        $invalidClaims = BankGuarantee::whereColumn('claim_amount_idr', '>', 'amount_idr')->count();

        // Validasi invariant loan: repaid tidak melampaui 150% principal
        $invalidLoans = TradeLoan::whereRaw('repaid_amount_idr > principal_amount_idr * 1.5')->count();

        $discrepancyCount = $invalidClaims + $invalidLoans;

        return [
            'status' => ($discrepancyCount === 0) ? 'OK' : 'DISCREPANCY',
            'discrepancy_count' => $discrepancyCount,
            'lc_count' => $lcs,
            'collection_count' => $collections,
            'guarantee_count' => $guarantees,
            'loan_count' => $loans,
        ];
    }
}
