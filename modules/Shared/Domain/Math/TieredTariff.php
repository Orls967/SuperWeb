<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use InvalidArgumentException;

/**
 * Progressive Tiered Utility Tariff Calculator with Time-Of-Use (TOU) Scaling.
 *
 * Implements piecewise-linear contiguous progressive blocks [minUnits, maxUnits)
 * where lower bound is inclusive and upper bound is exclusive.
 *
 * Complexity: O(B) where B is the number of tariff blocks.
 *
 * @author Google Deepmind & Antigravity
 */
final class TieredTariff
{
    /**
     * Calculate progressive utility bill across tiered blocks.
     *
     * @param  int  $consumption  Total units consumed (>= 0)
     * @param  list<TieredTariffBlock>  $blocks  Progressive tariff blocks
     * @param  int  $touMultiplierBps  Time-Of-Use multiplier in bps (10000 = 1.0x)
     *
     * @throws InvalidArgumentException
     */
    public static function calculate(
        int $consumption,
        array $blocks,
        int $touMultiplierBps = 10_000
    ): TieredTariffResult {
        if ($consumption < 0) {
            throw new InvalidArgumentException("Consumption cannot be negative: {$consumption}");
        }
        if ($touMultiplierBps < 0) {
            throw new InvalidArgumentException("TOU multiplier cannot be negative: {$touMultiplierBps}");
        }
        if (empty($blocks)) {
            throw new InvalidArgumentException('Tariff blocks cannot be empty');
        }

        // Validate contiguous blocks starting at 0
        $count = count($blocks);
        if ($blocks[0]->minUnits !== 0) {
            throw new InvalidArgumentException("First tariff block must start at minUnits 0, got {$blocks[0]->minUnits}");
        }

        for ($i = 0; $i < $count; $i++) {
            $cur = $blocks[$i];
            if ($i < $count - 1) {
                if ($cur->maxUnits === null) {
                    throw new InvalidArgumentException('Only the final tariff block can have an open upper bound (null)');
                }
                $next = $blocks[$i + 1];
                if ($cur->maxUnits !== $next->minUnits) {
                    throw new InvalidArgumentException("Tariff blocks must be contiguous: block {$i} maxUnits ({$cur->maxUnits}) != block ".($i + 1)." minUnits ({$next->minUnits})");
                }
            }
        }

        $tierBreakdowns = [];
        $totalCost = 0;
        $totalAllocatedUnits = 0;

        foreach ($blocks as $block) {
            if ($consumption <= $block->minUnits) {
                $unitsInTier = 0;
            } elseif ($block->maxUnits !== null) {
                $unitsInTier = min($consumption, $block->maxUnits) - $block->minUnits;
            } else {
                $unitsInTier = $consumption - $block->minUnits;
            }

            $baseCost = $unitsInTier * $block->ratePerUnit;
            $effectiveCost = ($touMultiplierBps === 10_000)
                ? $baseCost
                : FixedScale::mulDivHalfUp($baseCost, $touMultiplierBps, 10_000);

            $tierBreakdowns[] = [
                'minUnits' => $block->minUnits,
                'maxUnits' => $block->maxUnits,
                'units' => $unitsInTier,
                'ratePerUnit' => $block->ratePerUnit,
                'baseCost' => $baseCost,
                'effectiveCost' => $effectiveCost,
            ];

            $totalCost += $effectiveCost;
            $totalAllocatedUnits += $unitsInTier;
        }

        return new TieredTariffResult(
            totalUnits: $totalAllocatedUnits,
            totalCost: $totalCost,
            touMultiplierBps: $touMultiplierBps,
            tierBreakdowns: $tierBreakdowns
        );
    }
}
