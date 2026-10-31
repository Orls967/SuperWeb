<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\CloudKitchen\Domain\Models\KitchenRecipe;
use Modules\CloudKitchen\Domain\Models\RecipeIngredient;
use Modules\Shared\Domain\Math\DagExplosion;
use Modules\Shared\Domain\Math\Exceptions\CycleDetectedException;

class RecipeBomService
{
    /**
     * Create a new recipe with ingredient bill of materials.
     *
     * @param array{
     *     recipe_code: string,
     *     name: string,
     *     brand_name: string,
     *     selling_price_idr: int,
     *     estimated_prep_minutes?: int,
     *     ingredients?: list<array{
     *         ingredient_code?: string,
     *         child_recipe_id?: int,
     *         quantity_required: int,
     *         unit?: string,
     *         cost_per_unit_idr?: int
     *     }>
     * } $data
     */
    public function createRecipe(array $data): KitchenRecipe
    {
        return DB::transaction(function () use ($data): KitchenRecipe {
            $recipe = KitchenRecipe::create([
                'recipe_code' => $data['recipe_code'],
                'name' => $data['name'],
                'brand_name' => $data['brand_name'],
                'selling_price_idr' => $data['selling_price_idr'],
                'estimated_prep_minutes' => $data['estimated_prep_minutes'] ?? 20,
                'is_active' => true,
            ]);

            if (isset($data['ingredients'])) {
                foreach ($data['ingredients'] as $item) {
                    RecipeIngredient::create([
                        'parent_recipe_id' => $recipe->id,
                        'child_recipe_id' => $item['child_recipe_id'] ?? null,
                        'ingredient_code' => $item['ingredient_code'] ?? null,
                        'quantity_required' => $item['quantity_required'],
                        'unit' => $item['unit'] ?? 'gram',
                        'cost_per_unit_idr' => $item['cost_per_unit_idr'] ?? 0,
                    ]);
                }
            }

            return $recipe->load('ingredients');
        });
    }

    /**
     * Resolve raw materials using DagExplosion B0′.4 DAG traversal.
     *
     * @return array<string, int> Raw ingredient code => total quantity required
     *
     * @throws DomainException
     */
    public function resolveRawMaterials(int $recipeId, int $quantity = 1): array
    {
        $rootRecipe = KitchenRecipe::with('ingredients')->findOrFail($recipeId);

        // Build recipe graph from database
        $allRecipes = KitchenRecipe::with('ingredients')->get();
        $graph = [];

        foreach ($allRecipes as $r) {
            $nodeKey = "RCP-{$r->id}";
            $ingredients = [];

            foreach ($r->ingredients as $ing) {
                if ($ing->child_recipe_id !== null) {
                    $ingredients[] = [
                        'id' => "RCP-{$ing->child_recipe_id}",
                        'quantity' => $ing->quantity_required,
                    ];
                } elseif ($ing->ingredient_code !== null) {
                    $ingredients[] = [
                        'id' => $ing->ingredient_code,
                        'quantity' => $ing->quantity_required,
                    ];
                }
            }

            $graph[$nodeKey] = [
                'batch_yield' => 1,
                'ingredients' => $ingredients,
            ];
        }

        $demands = ["RCP-{$rootRecipe->id}" => $quantity];

        try {
            $result = DagExplosion::explode($demands, $graph);

            return $result->rawCeiledTotals;
        } catch (CycleDetectedException $e) {
            throw new DomainException('BOM recipe dependency cycle detected: '.implode(' -> ', $e->cyclePath), previous: $e);
        }
    }

    /**
     * Generate independent verification certificate for a recipe BOM explosion.
     */
    public function generateBOMCertificate(int $recipeId, int $quantity = 1): BOMVerificationCertificate
    {
        $rawTotals = $this->resolveRawMaterials($recipeId, $quantity);

        // Calculate total cost
        $totalCost = 0;
        $allIngredients = RecipeIngredient::whereNotNull('ingredient_code')->get()->keyBy('ingredient_code');

        foreach ($rawTotals as $code => $qty) {
            $ing = $allIngredients->get($code);
            $unitCost = $ing ? $ing->cost_per_unit_idr : 0;
            $totalCost += $qty * $unitCost;
        }

        // Re-run explosion to extract DagExplosionCertificate
        $allRecipes = KitchenRecipe::with('ingredients')->get();
        $graph = [];

        foreach ($allRecipes as $r) {
            $nodeKey = "RCP-{$r->id}";
            $ingredients = [];

            foreach ($r->ingredients as $ing) {
                if ($ing->child_recipe_id !== null) {
                    $ingredients[] = [
                        'id' => "RCP-{$ing->child_recipe_id}",
                        'quantity' => $ing->quantity_required,
                    ];
                } elseif ($ing->ingredient_code !== null) {
                    $ingredients[] = [
                        'id' => $ing->ingredient_code,
                        'quantity' => $ing->quantity_required,
                    ];
                }
            }

            $graph[$nodeKey] = [
                'batch_yield' => 1,
                'ingredients' => $ingredients,
            ];
        }

        $explosion = DagExplosion::explode(["RCP-{$recipeId}" => $quantity], $graph);

        return new BOMVerificationCertificate(
            recipeId: $recipeId,
            totalCostIdr: $totalCost,
            dagCertificate: $explosion->certificate,
            rawMaterials: $rawTotals
        );
    }
}
