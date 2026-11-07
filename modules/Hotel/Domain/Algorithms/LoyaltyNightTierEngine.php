<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

/**
 * Algoritma Bersertifikat H7: LoyaltyNightTierEngine.
 * Menghitung akumulasi malam menginap, perolehan poin dengan multiplier tier,
 * dan privilege profil tamu.
 */
final class LoyaltyNightTierEngine
{
    /**
     * Konversi dasar: Setiap kelipatan IDR 10.000 pembelanjaan kamar menghasilkan 10 poin dasar.
     */
    private const BASE_POINTS_PER_TEN_THOUSAND = 10;

    public function evaluate(
        string $guestId,
        int $priorNights,
        int $stayNights,
        int $eligibleSpendIdr,
        int $existingPointsBalance
    ): LoyaltyTierCertificate {
        $totalNights = max(0, $priorNights + $stayNights);

        // Kualifikasi Tier Berdasarkan Malam Menginap
        [$tierName, $multiplier, $privileges] = match (true) {
            $totalNights >= 50 => [
                'PLATINUM',
                1.50,
                [
                    'free_breakfast' => true,
                    'late_checkout_hours' => 4,
                    'room_upgrade_priority' => 'SUITE',
                    'lounge_access' => true,
                ],
            ],
            $totalNights >= 25 => [
                'GOLD',
                1.30,
                [
                    'free_breakfast' => true,
                    'late_checkout_hours' => 2,
                    'room_upgrade_priority' => 'NEXT_CATEGORY',
                    'lounge_access' => false,
                ],
            ],
            $totalNights >= 10 => [
                'SILVER',
                1.15,
                [
                    'free_breakfast' => false,
                    'late_checkout_hours' => 1,
                    'room_upgrade_priority' => 'PREFERRED_VIEW',
                    'lounge_access' => false,
                ],
            ],
            default => [
                'BASIC',
                1.00,
                [
                    'free_breakfast' => false,
                    'late_checkout_hours' => 0,
                    'room_upgrade_priority' => 'NONE',
                    'lounge_access' => false,
                ],
            ],
        };

        // Perhitungan Poin Diperoleh
        $baseSpendUnits = intdiv(max(0, $eligibleSpendIdr), 10000);
        $basePoints = $baseSpendUnits * self::BASE_POINTS_PER_TEN_THOUSAND;
        $pointsEarned = (int) round($basePoints * $multiplier);

        $totalActivePoints = max(0, $existingPointsBalance + $pointsEarned);

        $checksum = hash('sha256', sprintf(
            '%s|%d|%s|%.2f|%d|%d',
            $guestId,
            $totalNights,
            $tierName,
            $multiplier,
            $pointsEarned,
            $totalActivePoints
        ));

        return new LoyaltyTierCertificate(
            guestId: $guestId,
            totalNightsAccumulated: $totalNights,
            tierName: $tierName,
            pointsMultiplier: $multiplier,
            pointsEarnedThisStay: $pointsEarned,
            totalActivePoints: $totalActivePoints,
            privileges: $privileges,
            checksum: $checksum
        );
    }
}
