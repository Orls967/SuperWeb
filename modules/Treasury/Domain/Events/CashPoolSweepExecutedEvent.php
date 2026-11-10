<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Events;

use JsonSerializable;

/**
 * Event diterbitkan saat penyapuan kas pool notional dieksekusi antar rekening.
 */
final class CashPoolSweepExecutedEvent implements JsonSerializable
{
    public const EVENT_NAME = 'treasury.cash_pool.sweep_executed.v1';

    public function __construct(
        public string $poolId,
        public string $poolCode,
        public string $sourceAccountId,
        public string $targetAccountId,
        public int $sweepAmountMinor,
        public string $currency,
        public int $sourceBalanceAfterMinor,
        public int $targetBalanceAfterMinor,
        public string $postingReference,
        public ?int $actorId = null
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'event' => self::EVENT_NAME,
            'pool_id' => $this->poolId,
            'pool_code' => $this->poolCode,
            'source_account_id' => $this->sourceAccountId,
            'target_account_id' => $this->targetAccountId,
            'sweep_amount_minor' => $this->sweepAmountMinor,
            'currency' => $this->currency,
            'source_balance_after_minor' => $this->sourceBalanceAfterMinor,
            'target_balance_after_minor' => $this->targetBalanceAfterMinor,
            'posting_reference' => $this->postingReference,
            'actor_id' => $this->actorId,
        ];
    }
}
