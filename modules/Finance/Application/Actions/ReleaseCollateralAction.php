<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use Brick\Math\BigDecimal;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Finance\Domain\Enums\LoanStatus;
use Modules\Finance\Domain\Models\Loan;
use Modules\Shared\Application\BaseAction;

/**
 * Pinjaman lunas: kolateral kripto dikembalikan ke dompet peminjam.
 */
class ReleaseCollateralAction extends BaseAction
{
    public function __construct(
        private readonly Ledger $ledger,
    ) {}

    public function execute(Loan $loan, string $reason = 'Pelunasan pembiayaan'): Loan
    {
        $loan->loadMissing(['user', 'collateralAsset']);
        $qty = $loan->collateralQty();
        $symbol = $loan->collateralAsset->symbol;

        return $this->transaction(function () use ($loan, $qty, $symbol, $reason) {
            if ($qty->isPositive()) {
                $cryptoAccount = $loan->user->walletAccount($symbol);

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::LOAN_REPAYMENT->value,
                    description: "Pelepasan kolateral {$symbol} pembiayaan {$loan->uuid}: {$reason}",
                    idempotencyKey: 'loan_collateral_release_'.$loan->uuid,
                    entries: [
                        PostingEntryDTO::forCode("collateral:crypto:{$symbol}", $symbol, $qty->negated()),
                        PostingEntryDTO::forAccount($cryptoAccount->id, $symbol, $qty),
                    ],
                    referenceType: 'fin_loan',
                    referenceId: $loan->id,
                    meta: [
                        'loan_id' => $loan->id,
                        'collateral_qty' => $qty->__toString(),
                        'reason' => $reason,
                    ],
                    createdBy: $loan->user_id,
                    postedAt: now(),
                ));
            }

            $loan->collateral_qty = BigDecimal::zero()->__toString();
            $loan->closed_at = now();
            $loan->save();

            $loan->transitionTo(LoanStatus::PaidOff);

            return $loan->fresh();
        });
    }
}
