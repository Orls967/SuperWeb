<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use InvalidArgumentException;

/**
 * Deterministic Band-Based Portfolio Rebalancer with Greedy Lot Allocation.
 *
 * Sells assets exceeding upper tolerance band down towards target weight (rounded down to lot size),
 * then greedily buys lots for deficit assets until no further lot can be purchased.
 *
 * Complexity: O(A * (L + log A)) where A is asset count and L is number of lots traded.
 *
 * @author Google Deepmind & Antigravity
 */
final class PortfolioRebalancer
{
    /**
     * Rebalance portfolio deterministically according to target weights, bands, and lot constraints.
     *
     * @param  int  $cash  Available cash in IDR (integer rupiah)
     * @param  list<PortfolioAsset>  $assets  Portfolio holdings
     *
     * @throws InvalidArgumentException
     */
    public static function rebalance(int $cash, array $assets): PortfolioRebalanceResult
    {
        if ($cash < 0) {
            throw new InvalidArgumentException("Cash cannot be negative: {$cash}");
        }

        // Validate unique IDs and total target bps
        $assetMap = [];
        $totalTargetBps = 0;

        foreach ($assets as $asset) {
            if (isset($assetMap[$asset->id])) {
                throw new InvalidArgumentException("Duplicate asset ID: {$asset->id}");
            }
            $assetMap[$asset->id] = $asset;
            $totalTargetBps += $asset->targetBps;
        }

        if ($totalTargetBps > 10_000) {
            throw new InvalidArgumentException("Total target bps cannot exceed 10000: {$totalTargetBps}");
        }

        // Sort assets by ID ascending for PR3 Determinism
        ksort($assetMap);

        // Initial portfolio valuation
        $holdingsValue = 0;
        $currentUnits = [];
        foreach ($assetMap as $id => $asset) {
            $currentUnits[$id] = $asset->units;
            $holdingsValue += $asset->units * $asset->nav;
        }

        $totalPortfolioValue = $cash + $holdingsValue;
        if ($totalPortfolioValue <= 0) {
            return new PortfolioRebalanceResult(
                remainingCash: 0,
                units: $currentUnits,
                orders: [],
                initialTotalValue: 0,
                finalTotalValue: 0,
                finalWeightsBps: array_fill_keys(array_keys($assetMap), 0)
            );
        }

        $orders = [];
        $remainingCash = $cash;

        // Phase 1: Selling assets exceeding target + band
        foreach ($assetMap as $id => $asset) {
            $currentVal = $currentUnits[$id] * $asset->nav;
            $currentWeightBps = intdiv($currentVal * 10_000, $totalPortfolioValue);
            $upperBandBps = $asset->targetBps + $asset->bandBps;

            if ($currentWeightBps > $upperBandBps) {
                $targetVal = intdiv($totalPortfolioValue * $asset->targetBps, 10_000);
                $excessVal = $currentVal - $targetVal;
                $lotPrice = $asset->nav * $asset->lot;

                if ($excessVal >= $lotPrice) {
                    $lotsToSell = intdiv($excessVal, $lotPrice);
                    $unitsToSell = min($lotsToSell * $asset->lot, $currentUnits[$id]);
                    $actualLots = intdiv($unitsToSell, $asset->lot);

                    if ($actualLots > 0) {
                        $unitsSold = $actualLots * $asset->lot;
                        $proceeds = $unitsSold * $asset->nav;

                        $currentUnits[$id] -= $unitsSold;
                        $remainingCash += $proceeds;

                        $orders[] = [
                            'id' => $id,
                            'action' => 'SELL',
                            'lots' => $actualLots,
                            'units' => $unitsSold,
                            'pricePerUnit' => $asset->nav,
                            'totalAmount' => $proceeds,
                        ];
                    }
                }
            }
        }

        // Phase 2: Greedy Buying
        // Deterministically buy 1 lot for the asset with the largest value deficit
        // where deficit >= lot price AND cash >= lot price.
        // Tie-break: asset ID ascending (ensured by ksort earlier).
        $boughtLots = [];

        while (true) {
            $bestId = null;
            $bestDeficit = 0;
            $bestLotPrice = 0;

            foreach ($assetMap as $id => $asset) {
                $currentVal = $currentUnits[$id] * $asset->nav;
                $targetVal = intdiv($totalPortfolioValue * $asset->targetBps, 10_000);
                $deficit = $targetVal - $currentVal;
                $lotPrice = $asset->nav * $asset->lot;

                if ($deficit >= $lotPrice && $remainingCash >= $lotPrice) {
                    if ($bestId === null || $deficit > $bestDeficit) {
                        $bestId = $id;
                        $bestDeficit = $deficit;
                        $bestLotPrice = $lotPrice;
                    }
                }
            }

            if ($bestId === null) {
                break;
            }

            // Execute 1 lot purchase
            $asset = $assetMap[$bestId];
            $currentUnits[$bestId] += $asset->lot;
            $remainingCash -= $bestLotPrice;
            $boughtLots[$bestId] = ($boughtLots[$bestId] ?? 0) + 1;
        }

        // Record aggregated BUY orders
        foreach ($boughtLots as $id => $lots) {
            $asset = $assetMap[$id];
            $units = $lots * $asset->lot;
            $cost = $units * $asset->nav;

            $orders[] = [
                'id' => $id,
                'action' => 'BUY',
                'lots' => $lots,
                'units' => $units,
                'pricePerUnit' => $asset->nav,
                'totalAmount' => $cost,
            ];
        }

        // Compute final valuations and weights
        $finalHoldingsValue = 0;
        foreach ($assetMap as $id => $asset) {
            $finalHoldingsValue += $currentUnits[$id] * $asset->nav;
        }
        $finalTotalValue = $remainingCash + $finalHoldingsValue;

        $finalWeightsBps = [];
        foreach ($assetMap as $id => $asset) {
            $val = $currentUnits[$id] * $asset->nav;
            $finalWeightsBps[$id] = ($finalTotalValue > 0)
                ? intdiv($val * 10_000, $finalTotalValue)
                : 0;
        }

        return new PortfolioRebalanceResult(
            remainingCash: $remainingCash,
            units: $currentUnits,
            orders: $orders,
            initialTotalValue: $totalPortfolioValue,
            finalTotalValue: $finalTotalValue,
            finalWeightsBps: $finalWeightsBps
        );
    }
}
