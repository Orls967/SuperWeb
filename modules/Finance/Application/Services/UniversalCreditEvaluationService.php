<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Services;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use RuntimeException;

class UniversalCreditEvaluationService
{
    public function __construct(
        protected Ledger $ledger
    ) {}

    /**
     * Compute 360-degree composite credit score across 12 lines (0 - 1000)
     * Weights: Volume (30%), Repayment (25%), RWA Collateral (20%), Production (15%), Workforce/K3 (10%)
     *
     * @return array{score: int, tier: string, interest_rate_bps: int, max_limit_idr: int, status: string}
     */
    public function evaluateCreditProfile360(
        int $volumeScore,      // 0 - 1000
        int $repaymentScore,   // 0 - 1000
        int $rwaHoldingsScore, // 0 - 1000
        int $productionScore,  // 0 - 1000
        int $workforceScore    // 0 - 1000
    ): array {
        // Deterministic integer math without float
        $compositeScore = (int) floor(
            (30 * $volumeScore + 25 * $repaymentScore + 20 * $rwaHoldingsScore + 15 * $productionScore + 10 * $workforceScore) / 100
        );

        $compositeScore = max(0, min(1000, $compositeScore));

        if ($compositeScore >= 750) {
            return [
                'score' => $compositeScore,
                'tier' => 'TIER_A',
                'interest_rate_bps' => 650, // 6.5% p.a.
                'max_limit_idr' => 50_000_000_000, // Rp 50B
                'status' => 'APPROVED_AUTOMATIC',
            ];
        }

        if ($compositeScore >= 600) {
            return [
                'score' => $compositeScore,
                'tier' => 'TIER_B',
                'interest_rate_bps' => 850, // 8.5% p.a.
                'max_limit_idr' => 10_000_000_000, // Rp 10B
                'status' => 'APPROVED_AUTOMATIC',
            ];
        }

        if ($compositeScore >= 450) {
            return [
                'score' => $compositeScore,
                'tier' => 'TIER_C',
                'interest_rate_bps' => 1100, // 11.0% p.a.
                'max_limit_idr' => 2_500_000_000, // Rp 2.5B
                'status' => 'REQUIRES_RWA_COLLATERAL',
            ];
        }

        return [
            'score' => $compositeScore,
            'tier' => 'TIER_D',
            'interest_rate_bps' => 0,
            'max_limit_idr' => 0,
            'status' => 'REJECTED_INSUFFICIENT_SCORE',
        ];
    }

    /**
     * Disburse approved credit facility to borrower escrow
     *
     * @return array{loan_id: string, disbursed_idr: int, status: string}
     */
    public function disburseCreditFacility(
        string $loanId,
        string $borrowerId,
        int $principalAmountIdr,
        string $purposeCode
    ): array {
        if ($principalAmountIdr <= 0) {
            throw new RuntimeException('Loan principal amount must be positive.');
        }

        $idempotencyKey = 'loan_disb:'.hash('sha256', $loanId.':'.$borrowerId.':'.$purposeCode);

        // Double-entry: Debit Loan Receivable, Credit Borrower Escrow
        $this->ledger->post(new PostingDTO(
            type: TransactionType::LOAN_DISBURSEMENT->value,
            description: "Cross-line credit facility {$loanId} [{$purposeCode}] for {$borrowerId}",
            idempotencyKey: $idempotencyKey,
            entries: [
                PostingEntryDTO::forCode('lending:loan_receivable:default:IDR', 'IDR', -$principalAmountIdr),
                PostingEntryDTO::forCode('lending:disbursement_escrow:IDR', 'IDR', $principalAmountIdr),
            ],
            referenceType: 'credit_facilities',
            referenceId: (string) hash('crc32b', $loanId),
        ));

        return [
            'loan_id' => $loanId,
            'disbursed_idr' => $principalAmountIdr,
            'status' => 'DISBURSED',
        ];
    }
}
