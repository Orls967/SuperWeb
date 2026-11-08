<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

/**
 * H13: GlobalTierQualifier
 * Evaluasi status tier keanggotaan rolling 365 hari dengan kebijakan soft-landing downgrade.
 */
final class GlobalTierQualifier
{
    public const TIER_RANKS = [
        'SILVER' => 1,
        'GOLD' => 2,
        'PLATINUM' => 3,
        'BLACK' => 4,
    ];

    public function evaluate(
        int $rolling365Nights,
        int $rolling365SpendIdr,
        string $currentTier = 'SILVER',
        int $daysInGracePeriodRemaining = 0
    ): GlobalTierQualificationCertificate {
        $earnedTier = 'SILVER';

        if ($rolling365Nights >= 75 || $rolling365SpendIdr >= 250000000) {
            $earnedTier = 'BLACK';
        } elseif ($rolling365Nights >= 40 || $rolling365SpendIdr >= 100000000) {
            $earnedTier = 'PLATINUM';
        } elseif ($rolling365Nights >= 20 || $rolling365SpendIdr >= 35000000) {
            $earnedTier = 'GOLD';
        }

        $currentTier = strtoupper($currentTier);
        if (! isset(self::TIER_RANKS[$currentTier])) {
            $currentTier = 'SILVER';
        }

        $earnedRank = self::TIER_RANKS[$earnedTier];
        $currentRank = self::TIER_RANKS[$currentTier];

        $qualifiedTier = $earnedTier;
        $isUpgraded = false;
        $isDowngraded = false;
        $isGracePeriodActive = false;

        if ($earnedRank > $currentRank) {
            $qualifiedTier = $earnedTier;
            $isUpgraded = true;
        } elseif ($earnedRank < $currentRank) {
            if ($daysInGracePeriodRemaining > 0) {
                // Keep current tier during grace period
                $qualifiedTier = $currentTier;
                $isGracePeriodActive = true;
            } else {
                // Soft-landing policy: downgrade maximum 1 step down
                $newRank = max(1, $currentRank - 1);
                $tierFlip = array_flip(self::TIER_RANKS);
                $qualifiedTier = $tierFlip[$newRank] ?? 'SILVER';
                $isDowngraded = true;
            }
        }

        // Calculate progress to next tier
        $progressPercentage = 100.0;
        if ($qualifiedTier === 'SILVER') {
            $nightsProg = ($rolling365Nights / 20.0) * 100.0;
            $spendProg = ($rolling365SpendIdr / 35000000.0) * 100.0;
            $progressPercentage = min(100.0, max($nightsProg, $spendProg));
        } elseif ($qualifiedTier === 'GOLD') {
            $nightsProg = ($rolling365Nights / 40.0) * 100.0;
            $spendProg = ($rolling365SpendIdr / 100000000.0) * 100.0;
            $progressPercentage = min(100.0, max($nightsProg, $spendProg));
        } elseif ($qualifiedTier === 'PLATINUM') {
            $nightsProg = ($rolling365Nights / 75.0) * 100.0;
            $spendProg = ($rolling365SpendIdr / 250000000.0) * 100.0;
            $progressPercentage = min(100.0, max($nightsProg, $spendProg));
        }

        $payload = json_encode([
            'qualified_tier' => $qualifiedTier,
            'is_upgraded' => $isUpgraded,
            'is_downgraded' => $isDowngraded,
            'is_grace_period' => $isGracePeriodActive,
            'progress_percentage' => round($progressPercentage, 4),
        ], JSON_THROW_ON_ERROR);

        $signatureHash = hash('sha256', $payload);

        return new GlobalTierQualificationCertificate(
            qualifiedTier: $qualifiedTier,
            isUpgraded: $isUpgraded,
            isDowngraded: $isDowngraded,
            isGracePeriodActive: $isGracePeriodActive,
            progressToNextTierPercentage: $progressPercentage,
            signatureHash: $signatureHash
        );
    }
}
