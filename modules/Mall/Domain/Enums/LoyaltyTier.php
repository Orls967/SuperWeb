<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum LoyaltyTier: string
{
    case SILVER = 'silver';
    case GOLD = 'gold';
    case PLATINUM = 'platinum';

    public function label(): string
    {
        return match ($this) {
            self::SILVER => 'Silver Member',
            self::GOLD => 'Gold Member',
            self::PLATINUM => 'Platinum Member',
        };
    }

    /**
     * Multiplier penggali perolehan poin belanja.
     */
    public function multiplier(): float
    {
        return match ($this) {
            self::SILVER => 1.0,
            self::GOLD => 1.5,
            self::PLATINUM => 2.0,
        };
    }

    /**
     * Minimal akumulasi belanja (IDR) untuk mencapai tier ini.
     */
    public function minSpendThreshold(): int
    {
        return match ($this) {
            self::SILVER => 0,
            self::GOLD => 10_000_000,
            self::PLATINUM => 50_000_000,
        };
    }
}
