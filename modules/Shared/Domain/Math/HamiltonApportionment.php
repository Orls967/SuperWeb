<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Hamilton Method of Apportionment (Largest Remainder Method).
 *
 * Guarantees:
 * - Conservation invariant: sum(allocated) + unallocated === total
 * - Quota rule: floor(quota) <= allocated <= ceil(quota)
 * - Determinism: explicit ascending key tie-breaker
 * - Cap enforcement: allocation <= cap; when total >= sum(caps), full allocation + unallocated
 *
 * Complexity:
 * - Time: O(N log N) where N is count of items (sorting remainders)
 * - Space: O(N) auxiliary space
 */
final class HamiltonApportionment
{
    /**
     * @param  int  $total  Total units/amount to apportion (non-negative)
     * @param  list<array{key: string, weight: int|BigRational, cap?: int}>  $items
     *
     * @throws InvalidArgumentException
     */
    public static function apportion(int $total, array $items): HamiltonApportionmentResult
    {
        if ($total < 0) {
            throw new InvalidArgumentException("Total must be non-negative, got: {$total}");
        }

        if (empty($items)) {
            if ($total === 0) {
                $emptyCert = new HamiltonCertificate(0, 0, [], [], []);
                HamiltonCertificateVerifier::verify($emptyCert);

                return new HamiltonApportionmentResult([], 0, $emptyCert);
            }
            throw new InvalidArgumentException("Cannot apportion positive total ({$total}) among empty items");
        }

        $seenKeys = [];
        $allHaveCaps = true;
        $sumCaps = 0;
        $totalWeight = BigRational::zero();
        /** @var list<array{key: string, weight: BigRational, cap?: int}> $normalizedItems */
        $normalizedItems = [];

        foreach ($items as $item) {
            $key = $item['key'];
            if ($key === '') {
                throw new InvalidArgumentException('Item key cannot be empty');
            }
            if (isset($seenKeys[$key])) {
                throw new InvalidArgumentException("Duplicate key detected: {$key}");
            }
            $seenKeys[$key] = true;

            $rawWeight = $item['weight'];
            if ($rawWeight instanceof BigRational) {
                $weight = $rawWeight;
            } elseif (is_int($rawWeight)) {
                $weight = BigRational::of((string) $rawWeight);
            } else {
                throw new InvalidArgumentException("Weight for {$key} must be int or BigRational");
            }

            if ($weight->isLessThanOrEqualTo(0)) {
                throw new InvalidArgumentException("Weight for {$key} must be strictly positive");
            }

            $totalWeight = $totalWeight->plus($weight);

            $entry = [
                'key' => $key,
                'weight' => $weight,
            ];

            if (isset($item['cap'])) {
                if ($item['cap'] < 0) {
                    throw new InvalidArgumentException("Cap for {$key} must be non-negative");
                }
                $entry['cap'] = $item['cap'];
                $sumCaps += $item['cap'];
            } else {
                $allHaveCaps = false;
            }

            $normalizedItems[] = $entry;
        }

        // Cap rule: when total >= sum(caps) and all items have caps defined
        if ($allHaveCaps && $total >= $sumCaps) {
            $allocations = [];
            foreach ($normalizedItems as $item) {
                $allocations[$item['key']] = $item['cap'];
            }
            ksort($allocations);
            $unallocated = $total - $sumCaps;
            $cert = new HamiltonCertificate($total, $unallocated, $items, [], $allocations);
            HamiltonCertificateVerifier::verify($cert);

            return new HamiltonApportionmentResult($allocations, $unallocated, $cert);
        }

        if ($total === 0) {
            $allocations = [];
            $quotas = [];
            foreach ($normalizedItems as $item) {
                $allocations[$item['key']] = 0;
                $quotas[$item['key']] = BigRational::zero();
            }
            ksort($allocations);
            $cert = new HamiltonCertificate(0, 0, $items, $quotas, $allocations);
            HamiltonCertificateVerifier::verify($cert);

            return new HamiltonApportionmentResult($allocations, 0, $cert);
        }

        // Compute exact rational quotas
        $quotas = [];
        $allocations = [];
        $remainders = [];
        $sumFloors = 0;

        $totalRational = BigRational::of((string) $total);

        foreach ($normalizedItems as $item) {
            $key = $item['key'];
            $quota = $totalRational->multipliedBy($item['weight'])->dividedBy($totalWeight);
            $quotas[$key] = $quota;

            $floor = $quota->getNumerator()->dividedBy($quota->getDenominator(), RoundingMode::Floor)->toInt();
            if (isset($item['cap']) && $floor > $item['cap']) {
                $floor = $item['cap'];
            }

            $allocations[$key] = $floor;
            $sumFloors += $floor;

            $floorRational = BigRational::of((string) $floor);
            $fraction = $quota->minus($floorRational);

            $remainders[] = [
                'key' => $key,
                'fraction' => $fraction,
                'cap' => $item['cap'] ?? null,
            ];
        }

        $remainderToDistribute = $total - $sumFloors;

        // Sort by fractional part descending; tie-break on key ascending (PR3 Determinism)
        usort($remainders, function (array $a, array $b): int {
            /** @var BigRational $fracA */
            $fracA = $a['fraction'];
            /** @var BigRational $fracB */
            $fracB = $b['fraction'];

            $cmp = $fracB->compareTo($fracA);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['key'], (string) $b['key']);
        });

        foreach ($remainders as $rem) {
            if ($remainderToDistribute <= 0) {
                break;
            }
            $key = $rem['key'];
            $current = $allocations[$key];
            if ($rem['cap'] !== null && $current >= $rem['cap']) {
                continue;
            }
            $allocations[$key] = $current + 1;
            $remainderToDistribute--;
        }

        ksort($allocations);

        $certificate = new HamiltonCertificate($total, 0, $items, $quotas, $allocations);
        HamiltonCertificateVerifier::verify($certificate);

        return new HamiltonApportionmentResult($allocations, 0, $certificate);
    }
}
