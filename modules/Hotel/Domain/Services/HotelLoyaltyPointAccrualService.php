<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

/**
 * Layanan Kalkulasi Akrual Poin Loyalitas Menginap Tamu (Travel Pass Points).
 */
final class HotelLoyaltyPointAccrualService
{
    /**
     * @return array{points_earned: int, tier_multiplier: float, bonus_points: int}
     */
    public function calculateStayPoints(int $totalRoomChargePaidIdr, string $guestTier): array
    {
        // 1 poin per Rp 1.000 pembelanjaan kamar dasar
        $basePoints = (int) floor($totalRoomChargePaidIdr / 1000);

        $multiplier = 1.0;
        if ($guestTier === 'PLATINUM') {
            $multiplier = 2.0;
        } elseif ($guestTier === 'GOLD') {
            $multiplier = 1.5;
        } elseif ($guestTier === 'SILVER') {
            $multiplier = 1.2;
        }

        $totalPoints = (int) round($basePoints * $multiplier);
        $bonusPoints = $totalPoints - $basePoints;

        return [
            'points_earned' => $totalPoints,
            'tier_multiplier' => $multiplier,
            'bonus_points' => $bonusPoints,
        ];
    }
}
