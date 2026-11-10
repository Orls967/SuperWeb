<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Events;

use JsonSerializable;

/**
 * Event diterbitkan saat pencatatan voucher atau pengisian kembali kas kecil (imprest).
 */
final class PettyCashVoucherProcessedEvent implements JsonSerializable
{
    public const EVENT_NAME = 'treasury.petty_cash.voucher_processed.v1';

    public function __construct(
        public string $voucherId,
        public string $voucherNumber,
        public string $actionType,
        public int $amountMinor,
        public string $currency,
        public int $remainingBalanceMinor,
        public ?string $expenseCategory = null,
        public ?int $actorId = null
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'event' => self::EVENT_NAME,
            'voucher_id' => $this->voucherId,
            'voucher_number' => $this->voucherNumber,
            'action_type' => $this->actionType,
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency,
            'remaining_balance_minor' => $this->remainingBalanceMinor,
            'expense_category' => $this->expenseCategory,
            'actor_id' => $this->actorId,
        ];
    }
}
