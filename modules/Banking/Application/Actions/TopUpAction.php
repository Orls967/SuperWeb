<?php

declare(strict_types=1);

namespace Modules\Banking\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerTransaction;

class TopUpAction
{
    public const MAX_TOPUP_AMOUNT = 50_000_000;

    public function __construct(
        protected Ledger $ledger,
    ) {}

    public function execute(User $user, BigDecimal|string|int|float $amount, ?string $idempotencyKey = null): LedgerTransaction
    {
        $amountBd = $amount instanceof BigDecimal ? $amount : BigDecimal::of((string) $amount);

        if ($amountBd->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw new InvalidArgumentException('Nominal top up harus lebih besar dari 0.');
        }

        if ($amountBd->isGreaterThan(BigDecimal::of(self::MAX_TOPUP_AMOUNT))) {
            throw new InvalidArgumentException('Batas maksimal top up adalah Rp 50.000.000 per transaksi.');
        }

        $userWallet = $user->walletAccount('IDR');
        $key = $idempotencyKey ?? ('topup_'.$user->id.'_'.Str::random(16));

        $dto = new PostingDTO(
            type: TransactionType::TOPUP->value,
            description: 'Top Up Saldo Dompet IDR',
            idempotencyKey: $key,
            entries: [
                PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', $amountBd->negated()),
                PostingEntryDTO::forAccount($userWallet->id, 'IDR', $amountBd),
            ],
            referenceType: User::class,
            referenceId: $user->id,
            meta: [
                'user_id' => $user->id,
                'nominal' => $amountBd->__toString(),
            ],
            createdBy: $user->id,
            postedAt: now(),
        );

        return $this->ledger->post($dto);
    }
}
