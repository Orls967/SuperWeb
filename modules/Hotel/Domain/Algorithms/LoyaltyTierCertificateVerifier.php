<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

/**
 * Verifikator Matematis & Kebijakan Tier untuk LoyaltyTierCertificate.
 */
final class LoyaltyTierCertificateVerifier
{
    public function verify(LoyaltyTierCertificate $cert): bool
    {
        // 1. Verifikasi Threshold Tier
        $expectedTier = match (true) {
            $cert->totalNightsAccumulated >= 50 => 'PLATINUM',
            $cert->totalNightsAccumulated >= 25 => 'GOLD',
            $cert->totalNightsAccumulated >= 10 => 'SILVER',
            default => 'BASIC',
        };

        if ($cert->tierName !== $expectedTier) {
            return false;
        }

        // 2. Verifikasi Multiplier
        $expectedMultiplier = match ($expectedTier) {
            'PLATINUM' => 1.50,
            'GOLD' => 1.30,
            'SILVER' => 1.15,
            default => 1.00,
        };

        if (abs($cert->pointsMultiplier - $expectedMultiplier) > 0.001) {
            return false;
        }

        // 3. Verifikasi Checksum Hash
        $expectedChecksum = hash('sha256', sprintf(
            '%s|%d|%s|%.2f|%d|%d',
            $cert->guestId,
            $cert->totalNightsAccumulated,
            $cert->tierName,
            $cert->pointsMultiplier,
            $cert->pointsEarnedThisStay,
            $cert->totalActivePoints
        ));

        return hash_equals($expectedChecksum, $cert->checksum);
    }
}
