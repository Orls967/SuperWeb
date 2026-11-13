<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

/**
 * Kebijakan domain murni penentuan skor prioritas dinamis dan eskalasi SLA misi robotika.
 */
final class RoboticsMissionPriorityPolicy
{
    public const int MIN_PRIORITY = 1;

    public const int MAX_PRIORITY = 100;

    public const int SLA_BREACH_AGE_MINUTES = 45;

    /**
     * Menghitung prioritas efektif [1..100] berdasarkan prioritas dasar, usia antrean, retry, dan urgensi cutoff.
     */
    public function computeEffectivePriority(
        int $basePriority,
        int $queueAgeMinutes,
        int $retryCount,
        bool $carrierAtRisk,
    ): int {
        $ageBonus = min(20, intdiv(max(0, $queueAgeMinutes), 5));
        $retryBonus = min(15, max(0, $retryCount) * 8);
        $cutoffBonus = $carrierAtRisk ? 15 : 0;

        $effective = $basePriority + $ageBonus + $retryBonus + $cutoffBonus;

        return max(self::MIN_PRIORITY, min(self::MAX_PRIORITY, $effective));
    }

    /**
     * Menentukan apakah misi melewati batas SLA antrean/pergerakan dan perlu dieskalasi.
     */
    public function shouldEscalate(string $status, int $ageMinutes, int $retryCount): bool
    {
        if (in_array($status, ['completed', 'fallback_manual'], true)) {
            return false;
        }

        return $ageMinutes >= self::SLA_BREACH_AGE_MINUTES || $retryCount >= 2;
    }
}
