<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Enums;

/**
 * Tingkat performa keberlanjutan ESG penyewa/mitra untuk program Green Lease.
 */
enum EsgRatingTier: string
{
    case PLATINUM = 'PLATINUM';
    case GOLD = 'GOLD';
    case SILVER = 'SILVER';
    case BRONZE = 'BRONZE';
    case STANDARD = 'STANDARD';

    /**
     * Diskon tarif utilitas dalam basis points (1 bps = 0.01%).
     */
    public function utilityDiscountBps(): int
    {
        return match ($this) {
            self::PLATINUM => 1500, // Diskon 15%
            self::GOLD => 1000,     // Diskon 10%
            self::SILVER => 500,    // Diskon 5%
            self::BRONZE => 250,    // Diskon 2.5%
            self::STANDARD => 0,    // Tidak ada diskon
        };
    }

    /**
     * Skor ambang batas minimum (0 - 10000 bps).
     */
    public static function fromScore(int $scoreBps): self
    {
        return match (true) {
            $scoreBps >= 9000 => self::PLATINUM,
            $scoreBps >= 7500 => self::GOLD,
            $scoreBps >= 6000 => self::SILVER,
            $scoreBps >= 4500 => self::BRONZE,
            default => self::STANDARD,
        };
    }
}
