<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Resto\Domain\Enums\RecipeLineType;
use Modules\Resto\Domain\Exceptions\RecipeCycleDetected;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\IngredientCost;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Recipe;

class RecipeCostCalculator
{
    /**
     * Calculate cost per portion for a given MenuItem in an outlet.
     */
    public function calculateForMenuItem(MenuItem $item, ?int $outletId = null): array
    {
        $recipe = $item->recipe;

        if (! $recipe) {
            return [
                'has_recipe' => false,
                'batch_cost' => BigDecimal::zero(),
                'cost_per_portion' => BigDecimal::zero(),
                'cost_per_portion_idr' => 0,
                'margin_percent' => BigDecimal::zero(),
                'suggested_price' => $item->base_price,
            ];
        }

        return $this->calculateForRecipe($recipe, $outletId, (int) $item->priceForOutlet($outletId));
    }

    /**
     * Calculate cost for a recipe.
     *
     * @param  array<int>  $visitedRecipeIds
     * @return array{
     *     has_recipe: bool,
     *     batch_cost: BigDecimal,
     *     cost_per_portion: BigDecimal,
     *     cost_per_portion_idr: int,
     *     margin_percent: BigDecimal,
     *     suggested_price: int,
     *     lines: array<int, mixed>
     * }
     */
    public function calculateForRecipe(Recipe $recipe, ?int $outletId = null, int $sellingPrice = 0, array $visitedRecipeIds = []): array
    {
        if (in_array($recipe->id, $visitedRecipeIds, true)) {
            throw new RecipeCycleDetected("Ketergantungan sirkular terdeteksi pada resep ID {$recipe->id} ({$recipe->sub_recipe_name}).");
        }

        $visitedRecipeIds[] = $recipe->id;
        $batchCost = BigDecimal::zero();
        $lineDetails = [];

        foreach ($recipe->lines as $line) {
            if ($line->line_type === RecipeLineType::INGREDIENT && $line->ingredient_id) {
                $costPerBaseUnit = $this->getIngredientUnitCost((int) $line->ingredient_id, $outletId);
                $lineCost = $costPerBaseUnit->multipliedBy($line->quantity());
                $batchCost = $batchCost->plus($lineCost);

                $lineDetails[] = [
                    'type' => 'ingredient',
                    'name' => $line->ingredient?->name ?? 'Bahan',
                    'qty' => $line->quantity(),
                    'unit_cost' => $costPerBaseUnit,
                    'line_cost' => $lineCost,
                ];
            } elseif ($line->line_type === RecipeLineType::SUB_RECIPE && $line->sub_recipe_id) {
                $subRecipe = $line->subRecipe ?? Recipe::find($line->sub_recipe_id);
                if (! $subRecipe) {
                    continue;
                }

                $subResult = $this->calculateForRecipe($subRecipe, $outletId, 0, $visitedRecipeIds);
                $subBatchCost = $subResult['batch_cost'];
                $subYield = $subRecipe->yieldQuantity();

                $costPerYieldUnit = $subYield->isZero()
                    ? BigDecimal::zero()
                    : $subBatchCost->dividedBy($subYield, 6, RoundingMode::HalfUp);

                $lineCost = $costPerYieldUnit->multipliedBy($line->quantity());
                $batchCost = $batchCost->plus($lineCost);

                $lineDetails[] = [
                    'type' => 'sub_recipe',
                    'name' => $subRecipe->sub_recipe_name ?? 'Sub-Resep',
                    'qty' => $line->quantity(),
                    'unit_cost' => $costPerYieldUnit,
                    'line_cost' => $lineCost,
                ];
            }
        }

        // Apply waste percentage
        $wastePercent = $recipe->wastePercent();
        if ($wastePercent->isPositive()) {
            $wasteFactor = BigDecimal::one()->plus($wastePercent->dividedBy(BigDecimal::of(100), 6, RoundingMode::HalfUp));
            $batchCost = $batchCost->multipliedBy($wasteFactor);
        }

        $expectedPortions = $recipe->expectedPortions();
        $costPerPortion = $expectedPortions->isZero()
            ? BigDecimal::zero()
            : $batchCost->dividedBy($expectedPortions, 6, RoundingMode::HalfUp);

        $costPerPortionIdr = (int) $costPerPortion->toScale(0, RoundingMode::HalfUp)->toInt();

        // Calculate margin based on selling price
        $marginPercent = BigDecimal::zero();
        $suggestedPrice = (int) ceil($costPerPortionIdr * 1.5); // Default 50% markup target (33% margin)

        if ($sellingPrice > 0) {
            $marginAmount = BigDecimal::of($sellingPrice)->minus($costPerPortion);
            $marginPercent = $marginAmount->dividedBy(BigDecimal::of($sellingPrice), 4, RoundingMode::HalfUp)
                ->multipliedBy(BigDecimal::of(100));
        }

        return [
            'has_recipe' => true,
            'batch_cost' => $batchCost,
            'cost_per_portion' => $costPerPortion,
            'cost_per_portion_idr' => $costPerPortionIdr,
            'margin_percent' => $marginPercent,
            'suggested_price' => $suggestedPrice,
            'lines' => $lineDetails,
        ];
    }

    /**
     * Get unit cost per base unit for an ingredient.
     */
    protected function getIngredientUnitCost(int $ingredientId, ?int $outletId): BigDecimal
    {
        $query = IngredientCost::where('ingredient_id', $ingredientId);

        if ($outletId !== null) {
            $cost = (clone $query)->where('outlet_id', $outletId)->first();
            if ($cost && ! BigDecimal::of($cost->moving_avg_cost_per_base_unit ?: '0')->isZero()) {
                return BigDecimal::of($cost->moving_avg_cost_per_base_unit);
            }
            if ($cost && ! BigDecimal::of($cost->last_purchase_cost ?: '0')->isZero()) {
                return BigDecimal::of($cost->last_purchase_cost);
            }
        }

        // Global fallback across any outlet
        $cost = $query->where('moving_avg_cost_per_base_unit', '>', 0)->first();
        if ($cost) {
            return BigDecimal::of($cost->moving_avg_cost_per_base_unit);
        }

        return BigDecimal::zero();
    }
}
