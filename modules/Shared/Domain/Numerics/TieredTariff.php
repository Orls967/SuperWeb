<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;

/**
 * Calculates progressive tiered tariffs (block rates) for utility / data consumption.
 *
 * Complexity: O(T) where T is number of tiers.
 */
final class TieredTariff
{
    /**
     * @param  int  $units  Total units consumed (>= 0)
     * @param  list<array{up_to: int|null, rate: int}>  $tiers  List of tiers with strictly increasing up_to thresholds
     * @return array{total_cost: int, breakdown: list<array{tier_index: int, units: int, rate: int, cost: int}>}
     *
     * @throws InvalidArgumentException
     */
    public static function calculate(int $units, array $tiers): array
    {
        if ($units < 0) {
            throw new InvalidArgumentException("Units cannot be negative: {$units}");
        }

        if (empty($tiers)) {
            throw new InvalidArgumentException('Tiers cannot be empty.');
        }

        // Validate tiers
        $prevThreshold = 0;
        foreach ($tiers as $i => $tier) {
            if ($tier['rate'] < 0) {
                throw new InvalidArgumentException("Tier {$i} rate cannot be negative.");
            }

            if ($tier['up_to'] !== null) {
                if ($tier['up_to'] <= $prevThreshold) {
                    throw new InvalidArgumentException("Tier {$i} up_to ({$tier['up_to']}) must be strictly greater than previous threshold ({$prevThreshold}).");
                }
                $prevThreshold = $tier['up_to'];
            } else {
                if ($i !== count($tiers) - 1) {
                    throw new InvalidArgumentException('Only the final tier can have null up_to threshold.');
                }
            }
        }

        $remainingUnits = $units;
        $prevThreshold = 0;
        $totalCost = 0;
        $breakdown = [];

        foreach ($tiers as $i => $tier) {
            if ($remainingUnits <= 0) {
                $breakdown[] = [
                    'tier_index' => $i,
                    'units' => 0,
                    'rate' => $tier['rate'],
                    'cost' => 0,
                ];

                continue;
            }

            if ($tier['up_to'] === null) {
                $tierUnits = $remainingUnits;
            } else {
                $tierCapacity = $tier['up_to'] - $prevThreshold;
                $tierUnits = min($remainingUnits, $tierCapacity);
                $prevThreshold = $tier['up_to'];
            }

            $cost = $tierUnits * $tier['rate'];
            $totalCost += $cost;
            $remainingUnits -= $tierUnits;

            $breakdown[] = [
                'tier_index' => $i,
                'units' => $tierUnits,
                'rate' => $tier['rate'],
                'cost' => $cost,
            ];
        }

        $result = [
            'total_cost' => $totalCost,
            'breakdown' => $breakdown,
        ];

        TieredTariffVerifier::verify($units, $tiers, $result);

        return $result;
    }
}
