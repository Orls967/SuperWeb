<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use InvalidArgumentException;

/**
 * H15: BanquetBomExploder
 * Mentransformasikan resep menu BEO ke gramatur bahan mentah, varians penuangan minuman botolan,
 * dan kalkulasi shift tenaga kerja pramusaji dan koki.
 */
final class BanquetBomExploder
{
    /**
     * @param  array<int, array{
     *     item_id: string,
     *     name: string,
     *     type: string,
     *     portion_qty: float,
     *     unit: string,
     *     cost_per_unit_idr: int,
     *     yield_loss_ratio?: float,
     *     pour_shrinkage_ratio?: float,
     *     bottle_volume_ml?: int
     * }>  $recipeComponents
     */
    public function explode(
        string $beoId,
        int $guaranteedPax,
        array $recipeComponents,
        float $safetyBufferRatio = 1.05,
        float $eventDurationHours = 4.0,
        int $hourlyLaborRateIdr = 50000,
        string $secretKey = 'superweb-hotel-mice-salt'
    ): BanquetBomExplosionCertificate {
        if ($guaranteedPax <= 0) {
            throw new InvalidArgumentException('Guaranteed pax must be greater than zero.');
        }

        if ($safetyBufferRatio < 1.0) {
            throw new InvalidArgumentException('Safety buffer ratio must be at least 1.0.');
        }

        $preparedPax = (int) ceil($guaranteedPax * $safetyBufferRatio);

        $totalFoodCogsIdr = 0;
        $totalBeverageCogsIdr = 0;
        $ingredientsSummary = [];
        $beveragesSummary = [];

        foreach ($recipeComponents as $comp) {
            $type = strtolower($comp['type'] ?? 'food');
            $portion = (float) ($comp['portion_qty'] ?? 0);
            $costPerUnit = (int) ($comp['cost_per_unit_idr'] ?? 0);

            if ($type === 'beverage') {
                $shrinkage = (float) ($comp['pour_shrinkage_ratio'] ?? 0.05);
                $bottleVol = (int) ($comp['bottle_volume_ml'] ?? 1000);
                if ($bottleVol <= 0) {
                    $bottleVol = 1000;
                }

                $servingsNeeded = (int) ceil($preparedPax * (1.0 + $shrinkage));
                $totalVolumeMl = $servingsNeeded * $portion;
                $bottlesNeeded = (int) ceil($totalVolumeMl / $bottleVol);
                $lineCost = $bottlesNeeded * $costPerUnit;

                $totalBeverageCogsIdr += $lineCost;
                $beveragesSummary[] = [
                    'item_id' => $comp['item_id'],
                    'name' => $comp['name'],
                    'servings_needed' => $servingsNeeded,
                    'bottles_needed' => $bottlesNeeded,
                    'line_cost_idr' => $lineCost,
                ];
            } else {
                $yieldLoss = (float) ($comp['yield_loss_ratio'] ?? 0.0);
                if ($yieldLoss >= 1.0) {
                    $yieldLoss = 0.5;
                }

                $grossQty = ($preparedPax * $portion) / max(0.01, 1.0 - $yieldLoss);
                $lineCost = (int) round($grossQty * $costPerUnit);

                $totalFoodCogsIdr += $lineCost;
                $ingredientsSummary[] = [
                    'item_id' => $comp['item_id'],
                    'name' => $comp['name'],
                    'gross_qty' => round($grossQty, 2),
                    'unit' => $comp['unit'] ?? 'gram',
                    'line_cost_idr' => $lineCost,
                ];
            }
        }

        // Kebutuhan Staf: 1 pramusaji per 20 pax, 1 koki/steward per 40 pax
        $waitersRequired = (int) ceil($guaranteedPax / 20.0);
        $cooksRequired = (int) ceil($guaranteedPax / 40.0);
        $totalLaborHours = ($waitersRequired + $cooksRequired) * $eventDurationHours;
        $totalLaborCostIdr = (int) round($totalLaborHours * $hourlyLaborRateIdr);

        $totalDirectCostIdr = $totalFoodCogsIdr + $totalBeverageCogsIdr + $totalLaborCostIdr;

        $payload = implode('|', [
            $beoId,
            $guaranteedPax,
            $preparedPax,
            $totalFoodCogsIdr,
            $totalBeverageCogsIdr,
            $totalLaborCostIdr,
            $totalDirectCostIdr,
            $waitersRequired,
            $cooksRequired,
            json_encode($ingredientsSummary, JSON_UNESCAPED_SLASHES),
            json_encode($beveragesSummary, JSON_UNESCAPED_SLASHES),
        ]);

        $signatureHash = hash_hmac('sha256', $payload, $secretKey);

        return new BanquetBomExplosionCertificate(
            beoId: $beoId,
            guaranteedPax: $guaranteedPax,
            preparedPax: $preparedPax,
            totalFoodCogsIdr: $totalFoodCogsIdr,
            totalBeverageCogsIdr: $totalBeverageCogsIdr,
            totalLaborCostIdr: $totalLaborCostIdr,
            totalDirectCostIdr: $totalDirectCostIdr,
            waitersRequired: $waitersRequired,
            cooksRequired: $cooksRequired,
            ingredientsSummary: $ingredientsSummary,
            beveragesSummary: $beveragesSummary,
            signatureHash: $signatureHash
        );
    }
}
