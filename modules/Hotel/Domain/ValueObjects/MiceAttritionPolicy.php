<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\ValueObjects;

use InvalidArgumentException;

final class MiceAttritionPolicy
{
    public function __construct(
        public readonly float $allowedSlippagePercent = 15.0, // 15% slippage tolerated
        public readonly float $liquidatedDamagesRatePercent = 100.0, // 100% of single room rate or lost profit
        public readonly int $cancellationGracePeriodDays = 60
    ) {
        if ($this->allowedSlippagePercent < 0 || $this->allowedSlippagePercent > 50) {
            throw new InvalidArgumentException('Allowed slippage must be between 0% and 50%');
        }
    }

    public function calculatePenalty(int $contractedNights, int $actualNights, float $nightlyRate): float
    {
        $minimumRequired = (int) ceil($contractedNights * (1 - ($this->allowedSlippagePercent / 100.0)));
        if ($actualNights >= $minimumRequired) {
            return 0.0;
        }

        $shortfall = $minimumRequired - $actualNights;

        return round($shortfall * $nightlyRate * ($this->liquidatedDamagesRatePercent / 100.0), 2);
    }
}
