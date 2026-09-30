<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Services;

use Modules\Mall\Domain\Enums\UtilityType;
use Modules\Mall\Domain\Models\UtilityTariff;

class UtilityTariffCalculator
{
    /**
     * Hitung total tagihan utilitas (pemakaian berjenjang + beban tetap).
     */
    public function calculate(int $propertyId, UtilityType $type, float $usage): int
    {
        $tariffs = UtilityTariff::where('property_id', $propertyId)
            ->where('utility_type', $type)
            ->orderBy('tier_number', 'asc')
            ->get();

        if ($tariffs->isEmpty()) {
            $defaultRate = $type === UtilityType::ELECTRICITY ? 1650 : 9000;

            return (int) round($usage * $defaultRate);
        }

        $standingCharge = (int) ($tariffs->first()?->standing_charge ?? 0);
        $totalCost = $standingCharge;

        foreach ($tariffs as $tariff) {
            $min = (float) $tariff->tier_min;
            $max = $tariff->tier_max !== null ? (float) $tariff->tier_max : null;

            if ($usage <= $min) {
                continue;
            }

            $upper = $max !== null ? min($usage, $max) : $usage;
            $consumedInTier = max(0.0, $upper - $min);
            $tierAmount = (int) round($consumedInTier * $tariff->rate_per_unit);

            $totalCost += $tierAmount;
        }

        return $totalCost;
    }
}
