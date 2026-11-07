<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use InvalidArgumentException;

class HotelBanquetBOMExplosionService
{
    /**
     * Menghitung kuantitas bahan baku kotor berdasarkan resep, porsi, faktor susut, dan buffer prasmanan (+10%).
     *
     * @param  list<array{ingredient_name: string, net_gram_per_pax: float, shrink_factor_pct: float, unit_price_per_kg_idr: int}>  $recipeIngredients
     * @return array{pax_count: int, buffer_pax: int, total_prepared_pax: int, materials: list<array<string, mixed>>, total_food_cost_idr: int}
     */
    public function explodeBanquetRecipe(int $contractPax, array $recipeIngredients, bool $isBuffetStyle = true): array
    {
        if ($contractPax <= 0) {
            throw new InvalidArgumentException('Jumlah tamu jamuan harus lebih dari nol.');
        }

        // Buffer prasmanan 10% untuk mencegah kekurangan saji
        $bufferRatio = $isBuffetStyle ? 0.10 : 0.03;
        $bufferPax = (int) ceil($contractPax * $bufferRatio);
        $totalPreparedPax = $contractPax + $bufferPax;

        $materials = [];
        $totalCost = 0;

        foreach ($recipeIngredients as $ing) {
            $netGramPerPax = (float) ($ing['net_gram_per_pax'] ?? 0);
            $shrinkPct = (float) ($ing['shrink_factor_pct'] ?? 0);
            $unitPricePerKg = (int) ($ing['unit_price_per_kg_idr'] ?? 0);

            $netTotalKg = ($netGramPerPax * $totalPreparedPax) / 1000.0;
            $yieldRatio = max(0.1, 1.0 - ($shrinkPct / 100.0));
            $grossRequiredKg = round($netTotalKg / $yieldRatio, 2);

            $costIdr = (int) round($grossRequiredKg * $unitPricePerKg);
            $totalCost += $costIdr;

            $materials[] = [
                'name' => (string) ($ing['ingredient_name'] ?? 'Ingredient'),
                'net_kg' => round($netTotalKg, 2),
                'gross_kg' => $grossRequiredKg,
                'shrink_pct' => $shrinkPct,
                'unit_cost_kg_idr' => $unitPricePerKg,
                'subtotal_cost_idr' => $costIdr,
            ];
        }

        return [
            'pax_count' => $contractPax,
            'buffer_pax' => $bufferPax,
            'total_prepared_pax' => $totalPreparedPax,
            'materials' => $materials,
            'total_food_cost_idr' => $totalCost,
        ];
    }
}
