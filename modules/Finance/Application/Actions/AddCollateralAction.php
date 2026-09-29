<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Exception;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Finance\Domain\Models\Loan;
use Modules\Shared\Application\BaseAction;

/**
 * Tambah kolateral kripto untuk menurunkan LTV (mis. saat margin call).
 */
class AddCollateralAction extends BaseAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly EvaluateLoanRiskAction $evaluateRisk,
    ) {}

    public function execute(Loan $loan, User $user, string $qty): Loan
    {
        if ((int) $loan->user_id !== (int) $user->id && ! $user->isAdmin()) {
            throw new Exception('Kamu hanya dapat menambah kolateral pada pembiayaanmu sendiri.');
        }

        if (! $loan->status->isOpen()) {
            throw new Exception("Pinjaman berstatus {$loan->status->label()} tidak menerima tambahan kolateral.");
        }

        $addition = BigDecimal::of($qty);
        if (! $addition->isPositive()) {
            throw new Exception('Jumlah kolateral tambahan harus lebih besar dari 0.');
        }

        $loan->loadMissing('collateralAsset');
        $symbol = $loan->collateralAsset->symbol;
        $cryptoAccount = $loan->user->walletAccount($symbol);

        $holding = BigDecimal::of($cryptoAccount->cached_balance ?: '0');
        if ($holding->isLessThan($addition)) {
            throw new Exception("Holding {$symbol} tidak mencukupi. Tersedia {$holding->__toString()} {$symbol}.");
        }

        return $this->transaction(function () use ($loan, $symbol, $cryptoAccount, $addition) {
            $this->ledger->post(new PostingDTO(
                type: TransactionType::LOAN_DISBURSEMENT->value,
                description: "Penambahan kolateral {$symbol} pembiayaan {$loan->uuid}",
                idempotencyKey: 'loan_collateral_add_'.$loan->uuid.'_'.Str::random(10),
                entries: [
                    PostingEntryDTO::forAccount($cryptoAccount->id, $symbol, $addition->negated()),
                    PostingEntryDTO::forCode("collateral:crypto:{$symbol}", $symbol, $addition),
                ],
                referenceType: 'fin_loan',
                referenceId: $loan->id,
                meta: [
                    'loan_id' => $loan->id,
                    'added_qty' => $addition->__toString(),
                ],
                createdBy: $loan->user_id,
                postedAt: now(),
            ));

            $loan->collateral_qty = $loan->collateralQty()->plus($addition)->__toString();
            $loan->save();

            // LTV bisa kembali sehat setelah kolateral ditambah
            $this->evaluateRisk->evaluate($loan->fresh(['collateralAsset', 'user']));

            return $loan->fresh(['collateralAsset', 'installments']);
        });
    }
}
