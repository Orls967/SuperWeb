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
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;

class ManualAdjustmentAction
{
    public function __construct(
        protected Ledger $ledger,
    ) {}

    public function execute(
        LedgerAccount $account,
        BigDecimal|string|int|float $amount,
        string $reason,
        User $adminUser,
        ?string $idempotencyKey = null,
    ): LedgerTransaction {
        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw new InvalidArgumentException('Alasan penyesuaian manual wajib diisi.');
        }

        $amountBd = $amount instanceof BigDecimal ? $amount : BigDecimal::of((string) $amount);
        if ($amountBd->isZero()) {
            throw new InvalidArgumentException('Nominal penyesuaian manual tidak boleh 0.');
        }

        $asset = $account->asset_code;
        $clearingCode = "clearing:external:{$asset}";

        $key = $idempotencyKey ?? ('adj_'.$account->id.'_'.Str::random(16));

        $dto = new PostingDTO(
            type: TransactionType::MANUAL_ADJUSTMENT->value,
            description: "Penyesuaian manual untuk {$account->code}: {$trimmedReason}",
            idempotencyKey: $key,
            entries: [
                PostingEntryDTO::forCode($clearingCode, $asset, $amountBd->negated()),
                PostingEntryDTO::forAccount($account->id, $asset, $amountBd),
            ],
            referenceType: LedgerAccount::class,
            referenceId: $account->id,
            meta: [
                'reason' => $trimmedReason,
                'admin_id' => $adminUser->id,
                'admin_name' => $adminUser->name,
                'adjustment_amount' => $amountBd->__toString(),
            ],
            createdBy: $adminUser->id,
            postedAt: now(),
        );

        return $this->ledger->post($dto);
    }
}
