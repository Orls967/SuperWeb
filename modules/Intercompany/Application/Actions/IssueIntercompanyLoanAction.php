<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Application\Services\IntercompanyLedgerPoster;
use Modules\Intercompany\Domain\Algorithms\IntercompanyAmortizationScheduler;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\IntercompanyLoan;
use Modules\Intercompany\Domain\Models\IntercompanyLoanSchedule;
use Psr\Clock\ClockInterface;

class IssueIntercompanyLoanAction
{
    public function __construct(
        protected readonly IntercompanyAmortizationScheduler $scheduler,
        protected readonly IntercompanyLedgerPoster $ledgerPoster,
        protected readonly ?ClockInterface $clock = null
    ) {}

    /**
     * @param array{
     *     lender_entity: string,
     *     borrower_entity: string,
     *     principal_idr: int,
     *     base_rate_bps?: int,
     *     spread_bps?: int,
     *     withholding_tax_bps?: int,
     *     periods?: int,
     *     start_date?: string,
     *     due_date?: string,
     *     loan_agreement_number?: string,
     *     currency?: string,
     *     operator_id?: int|null,
     *     post_to_ledger?: bool
     * } $data
     */
    public function execute(array $data): IntercompanyLoan
    {
        $lender = $data['lender_entity'];
        $borrower = $data['borrower_entity'];
        $principal = (int) $data['principal_idr'];
        $currency = strtoupper($data['currency'] ?? 'IDR');

        if ($lender === $borrower) {
            throw new InvalidArgumentException('Pinjaman intercompany memerlukan entitas peminjam dan pemberi pinjaman berbeda.');
        }

        if ($principal <= 0) {
            throw new InvalidArgumentException('Pokok pinjaman harus lebih besar dari nol.');
        }

        $now = $this->clock !== null ? $this->clock->now() : new \DateTimeImmutable;
        $startDate = $data['start_date'] ?? $now->format('Y-m-d');
        $dueDate = $data['due_date'] ?? $now->modify('+1 year')->format('Y-m-d');

        $baseRateBps = $data['base_rate_bps'] ?? 600;
        $spreadBps = $data['spread_bps'] ?? 50;
        $whtBps = $data['withholding_tax_bps'] ?? 1500;
        $periods = $data['periods'] ?? 12;

        $hash = hash('sha256', "{$lender}:{$borrower}:{$principal}:{$startDate}:".$now->format('U'));
        $loanNumber = $data['loan_agreement_number'] ?? 'ICL-'.$now->format('Ymd').'-'.strtoupper(substr($hash, 0, 8));

        $lenderEntity = IntercompanyEntity::where('entity_code', $lender)->first();
        $borrowerEntity = IntercompanyEntity::where('entity_code', $borrower)->first();

        $loan = IntercompanyLoan::create([
            'loan_agreement_number' => $loanNumber,
            'lender_entity' => $lender,
            'borrower_entity' => $borrower,
            'lender_entity_id' => $lenderEntity?->id,
            'borrower_entity_id' => $borrowerEntity?->id,
            'currency' => $currency,
            'principal_idr' => $principal,
            'principal_minor' => $principal,
            'interest_rate_bps' => $baseRateBps,
            'spread_bps' => $spreadBps,
            'withholding_tax_bps' => $whtBps,
            'arms_length_interest_rate' => ($baseRateBps + $spreadBps) / 100.0,
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'repaid_principal_idr' => 0,
            'repaid_principal_minor' => 0,
            'status' => 'active',
        ]);

        // Generate skedul amortisasi deterministik (D6)
        $scheduleResult = $this->scheduler->generateSchedule(
            principalMinor: $principal,
            baseRateBps: $baseRateBps,
            spreadBps: $spreadBps,
            withholdingTaxBps: $whtBps,
            periods: $periods,
            startDate: $startDate
        );

        foreach ($scheduleResult->schedules as $item) {
            IntercompanyLoanSchedule::create([
                'loan_id' => $loan->id,
                'installment_number' => $item->periodNumber,
                'due_date' => $item->dueDate,
                'beginning_balance_minor' => $item->beginningBalanceMinor,
                'principal_due_minor' => $item->principalDueMinor,
                'interest_due_minor' => $item->interestGrossMinor,
                'tax_withheld_minor' => $item->taxWithheldMinor,
                'net_interest_minor' => $item->interestNetMinor,
                'ending_balance_minor' => $item->endingBalanceMinor,
                'payment_status' => 'pending',
            ]);
        }

        $postToLedger = $data['post_to_ledger'] ?? true;
        if ($postToLedger) {
            $ledgerTx = $this->ledgerPoster->postLoanDisbursement(
                lenderEntity: $lender,
                borrowerEntity: $borrower,
                principalMinor: $principal,
                currency: $currency,
                loanNumber: $loanNumber,
                idempotencyKey: "IC:LOAN:DISBURSE:{$loanNumber}",
                userId: $data['operator_id'] ?? null
            );

            $loan->ledger_transaction_id = $ledgerTx->id;
            $loan->save();
        }

        return $loan;
    }
}
