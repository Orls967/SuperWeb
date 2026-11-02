<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;

/**
 * Hamilton Method of Apportionment (Largest Remainder Method).
 *
 * Distributes an integer amount proportionally according to weights,
 * guaranteeing that the sum of allocated parts equals the total amount.
 * Tie-break is deterministic: item key/code in ascending order.
 *
 * Complexity: O(N log N) where N is the number of shares (sorting remainders).
 */
final class HamiltonApportionment
{
    /**
     * @param  int  $totalAmount  Total integer amount to apportion (>= 0)
     * @param  array<string|int, int>  $weights  Map of item identifier to integer weight (>= 0)
     * @return array<string|int, int> Map of item identifier to apportioned integer amount
     *
     * @throws InvalidArgumentException
     */
    public static function apportion(int $totalAmount, array $weights): array
    {
        if ($totalAmount < 0) {
            throw new InvalidArgumentException('Total amount to apportion cannot be negative.');
        }

        if (empty($weights)) {
            return [];
        }

        $totalWeight = 0;
        foreach ($weights as $key => $weight) {
            if ($weight < 0) {
                throw new InvalidArgumentException("Weight for '{$key}' cannot be negative.");
            }
            $totalWeight += $weight;
        }

        if ($totalWeight === 0) {
            if ($totalAmount > 0) {
                throw new InvalidArgumentException('Total weight must be positive when total amount is greater than zero.');
            }
            $zeroResult = [];
            foreach ($weights as $k => $v) {
                $zeroResult[$k] = 0;
            }

            return $zeroResult;
        }

        if ($totalAmount === 0) {
            $zeroResult = [];
            foreach ($weights as $k => $v) {
                $zeroResult[$k] = 0;
            }

            return $zeroResult;
        }

        $allocated = [];
        $remainders = [];
        $totalAllocated = 0;

        foreach ($weights as $key => $weight) {
            $product = $totalAmount * $weight;
            $floor = intdiv($product, $totalWeight);
            $rem = $product % $totalWeight;

            $allocated[$key] = $floor;
            $remainders[$key] = $rem;
            $totalAllocated += $floor;
        }

        $surplus = $totalAmount - $totalAllocated;

        if ($surplus > 0) {
            // Sort remainders descending.
            // Tie-break: string representation of key ascending.
            $keys = array_keys($weights);
            usort($keys, function ($a, $b) use ($remainders): int {
                $remA = $remainders[$a];
                $remB = $remainders[$b];
                if ($remA !== $remB) {
                    return $remB <=> $remA; // Descending remainder
                }

                return strcmp((string) $a, (string) $b); // Ascending key
            });

            for ($i = 0; $i < $surplus; $i++) {
                $key = $keys[$i];
                $allocated[$key]++;
            }
        }

        HamiltonApportionmentVerifier::verify($totalAmount, $weights, $allocated);

        return $allocated;
    }
}
