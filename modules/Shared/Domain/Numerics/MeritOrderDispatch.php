<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;

/**
 * Merit Order Dispatch algorithm.
 * Fulfills minimum capacity of all active units, then allocates remaining demand
 * in ascending order of marginal cost (tie-break: unit code ascending).
 *
 * Complexity: O(U log U) where U is the number of active units.
 */
final class MeritOrderDispatch
{
    /**
     * @param  list<array{code: string, min_mw: int, max_mw: int, marginal_cost: int, startup_cost: int}>  $activeUnits
     * @param  int  $demandMW  Required demand in MW (>= 0)
     * @return array{dispatches: array<string, int>, cost: int}|null Null if demand cannot be met by active units
     *
     * @throws InvalidArgumentException
     */
    public static function dispatch(array $activeUnits, int $demandMW): ?array
    {
        if ($demandMW < 0) {
            throw new InvalidArgumentException("Demand cannot be negative: {$demandMW}");
        }

        if (empty($activeUnits)) {
            if ($demandMW === 0) {
                return ['dispatches' => [], 'cost' => 0];
            }

            return null;
        }

        $sumMin = 0;
        $sumMax = 0;
        foreach ($activeUnits as $unit) {
            if ($unit['min_mw'] < 0 || $unit['max_mw'] < $unit['min_mw'] || $unit['marginal_cost'] < 0) {
                throw new InvalidArgumentException("Invalid unit parameters for unit '{$unit['code']}'.");
            }
            $sumMin += $unit['min_mw'];
            $sumMax += $unit['max_mw'];
        }

        if ($demandMW < $sumMin || $demandMW > $sumMax) {
            return null;
        }

        // Sort active units by marginal_cost ascending, tie-break by code ascending
        $sortedUnits = $activeUnits;
        usort($sortedUnits, function (array $a, array $b): int {
            if ($a['marginal_cost'] !== $b['marginal_cost']) {
                return $a['marginal_cost'] <=> $b['marginal_cost'];
            }

            return strcmp($a['code'], $b['code']);
        });

        $dispatches = [];
        foreach ($sortedUnits as $unit) {
            $dispatches[$unit['code']] = $unit['min_mw'];
        }

        $remaining = $demandMW - $sumMin;

        foreach ($sortedUnits as $unit) {
            if ($remaining <= 0) {
                break;
            }
            $headroom = $unit['max_mw'] - $unit['min_mw'];
            $allocation = min($remaining, $headroom);
            $dispatches[$unit['code']] += $allocation;
            $remaining -= $allocation;
        }

        $cost = 0;
        foreach ($sortedUnits as $unit) {
            $cost += $dispatches[$unit['code']] * $unit['marginal_cost'];
        }

        return [
            'dispatches' => $dispatches,
            'cost' => $cost,
        ];
    }
}
