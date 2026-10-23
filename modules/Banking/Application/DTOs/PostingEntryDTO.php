<?php

declare(strict_types=1);

namespace Modules\Banking\Application\DTOs;

use Brick\Math\BigDecimal;
use Modules\Shared\Domain\ValueObjects\Money;

final readonly class PostingEntryDTO
{
    public BigDecimal $amount;

    public function __construct(
        public ?int $accountId,
        public ?string $accountCode,
        public string $assetCode,
        BigDecimal|string|int|Money $amount,
    ) {
        if (is_float($amount)) {
            throw new \InvalidArgumentException('Nominal posting entry tidak boleh bertipe float; gunakan int, string, BigDecimal, atau Money.');
        }

        if ($amount instanceof Money) {
            $this->amount = $amount->amount;
        } elseif ($amount instanceof BigDecimal) {
            $this->amount = $amount;
        } else {
            $this->amount = BigDecimal::of((string) $amount);
        }
    }

    public static function forAccount(int $accountId, string $assetCode, BigDecimal|string|int|Money $amount): self
    {
        return new self(
            accountId: $accountId,
            accountCode: null,
            assetCode: $assetCode,
            amount: $amount,
        );
    }

    public static function forCode(string $accountCode, string $assetCode, BigDecimal|string|int|Money $amount): self
    {
        return new self(
            accountId: null,
            accountCode: $accountCode,
            assetCode: $assetCode,
            amount: $amount,
        );
    }
}
