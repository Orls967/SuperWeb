<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Resto\Domain\Enums\BatchStatus;
use Modules\Resto\Domain\Enums\RecipeLineType;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Exceptions\RecipeCycleDetected;
use Modules\Resto\Domain\Exceptions\ShortageException;
use Modules\Resto\Domain\Models\BatchConsumption;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\IngredientCost;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\ProductionBatch;
use Modules\Resto\Domain\Models\Recipe;

class CookBatchAction
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly Ledger $ledger
    ) {}

    /**
     * @param  array<int, array{ingredient_id: int, qty_base_unit: string}>|null  $customConsumptions
     *
     * @throws ShortageException
     */
    public function handle(
        int $outletId,
        int $recipeId,
        int $plannedPortions,
        ?int $actualPortions = null,
        ?array $customConsumptions = null,
        ?int $producedBy = null,
        ?string $note = null,
        bool $putOnDisplay = true
    ): ProductionBatch {
        $outlet = Outlet::findOrFail($outletId);
        $recipe = Recipe::with('lines.ingredient', 'lines.subRecipe.lines')->findOrFail($recipeId);

        $actualPortionsCount = $actualPortions ?? $plannedPortions;
        if ($plannedPortions <= 0 || $actualPortionsCount <= 0) {
            throw new \InvalidArgumentException('Jumlah porsi produksi harus lebih besar dari 0.');
        }

        // 1. Calculate required ingredients from recipe (recursively flattening sub-recipes)
        $expected = $recipe->expectedPortions();
        $scaleFactor = $expected->isPositive()
            ? BigDecimal::of($plannedPortions)->dividedBy($expected, 6, RoundingMode::HalfUp)
            : BigDecimal::one();

        /** @var array<int, array{ingredient: Ingredient, qty: BigDecimal}> $requiredIngredients */
        $requiredIngredients = [];
        $this->resolveIngredientsRecursive($recipe, $scaleFactor, $requiredIngredients);

        // 2. Check ingredient availability & collect shortages
        $shortages = [];
        $maxPortionsPerIngredient = [];

        foreach ($requiredIngredients as $ingId => $data) {
            $neededQty = $data['qty'];
            $availableStr = $this->inventoryService->availableIngredient($ingId, $outletId);
            $availableQty = BigDecimal::of($availableStr);

            if ($availableQty->isLessThan($neededQty)) {
                $shortageAmount = $neededQty->minus($availableQty);
                $shortages[] = [
                    'ingredient_id' => $ingId,
                    'ingredient_name' => $data['ingredient']->name,
                    'needed_base_unit' => $neededQty->toScale(6, RoundingMode::HalfUp)->__toString(),
                    'available_base_unit' => $availableQty->toScale(6, RoundingMode::HalfUp)->__toString(),
                    'shortage_base_unit' => $shortageAmount->toScale(6, RoundingMode::HalfUp)->__toString(),
                    'base_unit' => $data['ingredient']->base_unit,
                ];
            }

            // Calculate max portions this ingredient allows
            $qtyPerPortion = $neededQty->dividedBy(BigDecimal::of($plannedPortions), 6, RoundingMode::HalfUp);
            if ($qtyPerPortion->isPositive()) {
                $maxPossible = (int) floor((float) $availableQty->dividedBy($qtyPerPortion, 6, RoundingMode::Down)->__toString());
                $maxPortionsPerIngredient[] = max(0, $maxPossible);
            }
        }

        if (! empty($shortages) && empty($customConsumptions)) {
            $suggestedMaxPortions = ! empty($maxPortionsPerIngredient) ? min($maxPortionsPerIngredient) : 0;
            throw new ShortageException(
                shortages: $shortages,
                suggestedMaxPortions: $suggestedMaxPortions,
                message: "Stok bahan tidak mencukupi untuk memasak {$plannedPortions} porsi di outlet {$outlet->name}."
            );
        }

        // 3. Execute cooking inside DB transaction
        return DB::transaction(function () use (
            $outlet,
            $recipe,
            $plannedPortions,
            $actualPortionsCount,
            $requiredIngredients,
            $customConsumptions,
            $producedBy,
            $note,
            $putOnDisplay
        ) {
            $batchNo = sprintf(
                'BATCH-%s-%s-%s',
                $outlet->code ?: 'OUT',
                now()->format('Ymd'),
                strtoupper(substr((string) Str::uuid(), 0, 6))
            );

            $batch = ProductionBatch::create([
                'uuid' => (string) Str::uuid(),
                'outlet_id' => $outlet->id,
                'recipe_id' => $recipe->id,
                'menu_item_id' => $recipe->menu_item_id,
                'batch_no' => $batchNo,
                'planned_portions' => $plannedPortions,
                'actual_portions' => $actualPortionsCount,
                'cooked_at' => now(),
                'expires_at' => now()->addHours(DisplayTray::MAX_DISPLAY_HOURS),
                'status' => BatchStatus::READY,
                'cost_total' => 0,
                'cost_per_portion' => 0,
                'produced_by' => $producedBy,
                'note' => $note,
            ]);

            $totalBatchCost = BigDecimal::zero();

            // Use custom consumptions or calculated recipe requirements
            if (! empty($customConsumptions)) {
                foreach ($customConsumptions as $custom) {
                    $ingId = (int) $custom['ingredient_id'];
                    $qty = BigDecimal::of($custom['qty_base_unit']);
                    $costPerBaseUnit = $this->getIngredientUnitCost($ingId, $outlet->id);
                    $lineCost = $costPerBaseUnit->multipliedBy($qty);
                    $totalBatchCost = $totalBatchCost->plus($lineCost);

                    $this->inventoryService->deductIngredient(
                        ingredientId: $ingId,
                        outletId: $outlet->id,
                        qtyBaseUnit: $qty->toScale(6, RoundingMode::HalfUp)->__toString(),
                        reason: StockMovementReason::PRODUCTION,
                        sourceType: 'resto_batch',
                        sourceId: $batch->id,
                        note: "Konsumsi batch {$batch->batch_no}",
                        userId: $producedBy
                    );

                    BatchConsumption::create([
                        'batch_id' => $batch->id,
                        'ingredient_id' => $ingId,
                        'qty_base_unit' => $qty->toScale(6, RoundingMode::HalfUp)->__toString(),
                        'unit_cost' => $costPerBaseUnit->toScale(6, RoundingMode::HalfUp)->__toString(),
                        'line_cost' => (int) $lineCost->toScale(0, RoundingMode::HalfUp)->toInt(),
                    ]);
                }
            } else {
                foreach ($requiredIngredients as $ingId => $data) {
                    $qty = $data['qty'];
                    $costPerBaseUnit = $this->getIngredientUnitCost($ingId, $outlet->id);
                    $lineCost = $costPerBaseUnit->multipliedBy($qty);
                    $totalBatchCost = $totalBatchCost->plus($lineCost);

                    $this->inventoryService->deductIngredient(
                        ingredientId: $ingId,
                        outletId: $outlet->id,
                        qtyBaseUnit: $qty->toScale(6, RoundingMode::HalfUp)->__toString(),
                        reason: StockMovementReason::PRODUCTION,
                        sourceType: 'resto_batch',
                        sourceId: $batch->id,
                        note: "Konsumsi resep batch {$batch->batch_no}",
                        userId: $producedBy
                    );

                    BatchConsumption::create([
                        'batch_id' => $batch->id,
                        'ingredient_id' => $ingId,
                        'qty_base_unit' => $qty->toScale(6, RoundingMode::HalfUp)->__toString(),
                        'unit_cost' => $costPerBaseUnit->toScale(6, RoundingMode::HalfUp)->__toString(),
                        'line_cost' => (int) $lineCost->toScale(0, RoundingMode::HalfUp)->toInt(),
                    ]);
                }
            }

            $totalCostInt = (int) $totalBatchCost->toScale(0, RoundingMode::HalfUp)->toInt();
            $costPerPortionInt = $actualPortionsCount > 0
                ? (int) ceil($totalCostInt / $actualPortionsCount)
                : 0;

            $batch->cost_total = $totalCostInt;
            $batch->cost_per_portion = $costPerPortionInt;

            // 4. Double-entry ledger posting:
            // Internal inventory reclassification: raw ingredients -> finished goods
            // Account inventory:resto:{outlet}:IDR doesn't change in total value, sum entries = 0
            if ($totalCostInt > 0) {
                $outletCode = $outlet->code ?: "OUT-{$outlet->id}";
                $invAccCode = "inventory:resto:{$outletCode}:IDR";
                $this->ensureLedgerAccountExists($invAccCode, "Persediaan Resto {$outlet->name}", AccountKind::INVENTORY);

                $costBd = BigDecimal::of($totalCostInt);

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::PRODUCTION->value,
                    description: "Produksi batch {$batch->batch_no} ({$actualPortionsCount} porsi)",
                    idempotencyKey: "resto:batch:cook:{$batch->uuid}",
                    entries: [
                        PostingEntryDTO::forCode($invAccCode, 'IDR', $costBd->negated()), // Raw materials credit
                        PostingEntryDTO::forCode($invAccCode, 'IDR', $costBd),           // Finished goods debit
                    ],
                    referenceType: 'resto_batch',
                    referenceId: $batch->id,
                    createdBy: $producedBy
                ));
            }

            // 5. If putOnDisplay and menu item exists, create display tray
            if ($putOnDisplay && $recipe->menu_item_id) {
                DisplayTray::create([
                    'uuid' => (string) Str::uuid(),
                    'outlet_id' => $outlet->id,
                    'batch_id' => $batch->id,
                    'menu_item_id' => $recipe->menu_item_id,
                    'portions_remaining' => $actualPortionsCount,
                    'recirculation_count' => 0,
                    'placed_at' => now(),
                    'expires_at' => now()->addHours(DisplayTray::MAX_DISPLAY_HOURS),
                    'status' => TrayStatus::ON_DISPLAY,
                    'cost_per_portion' => $costPerPortionInt,
                ]);

                $batch->status = BatchStatus::ON_DISPLAY;
            }

            $batch->save();

            return $batch;
        }, attempts: 3);
    }

    /**
     * Recursively resolve all base ingredients required for a recipe, accounting for waste factor.
     *
     * @param  array<int, array{ingredient: Ingredient, qty: BigDecimal}>  $result
     * @param  array<int>  $visitedRecipeIds
     */
    private function resolveIngredientsRecursive(Recipe $recipe, BigDecimal $scaleFactor, array &$result, array $visitedRecipeIds = []): void
    {
        if (in_array($recipe->id, $visitedRecipeIds, true)) {
            throw new RecipeCycleDetected("Ketergantungan sirkular resep terdeteksi pada ID #{$recipe->id}.");
        }
        $visitedRecipeIds[] = $recipe->id;

        $wastePercent = $recipe->wastePercent();
        $wasteMultiplier = $wastePercent->isPositive()
            ? BigDecimal::one()->plus($wastePercent->dividedBy(BigDecimal::of(100), 6, RoundingMode::HalfUp))
            : BigDecimal::one();

        foreach ($recipe->lines as $line) {
            $effectiveQty = $line->quantity()->multipliedBy($scaleFactor)->multipliedBy($wasteMultiplier);

            if ($line->line_type === RecipeLineType::INGREDIENT && $line->ingredient_id) {
                $ingId = (int) $line->ingredient_id;
                $ingredient = $line->ingredient ?? Ingredient::findOrFail($ingId);

                if (! isset($result[$ingId])) {
                    $result[$ingId] = [
                        'ingredient' => $ingredient,
                        'qty' => BigDecimal::zero(),
                    ];
                }
                $result[$ingId]['qty'] = $result[$ingId]['qty']->plus($effectiveQty);
            } elseif ($line->line_type === RecipeLineType::SUB_RECIPE && $line->sub_recipe_id) {
                $subRecipe = $line->subRecipe ?? Recipe::with('lines.ingredient')->findOrFail($line->sub_recipe_id);
                $subYield = $subRecipe->yieldQuantity();

                $subScaleFactor = $subYield->isPositive()
                    ? $effectiveQty->dividedBy($subYield, 6, RoundingMode::HalfUp)
                    : BigDecimal::one();

                $this->resolveIngredientsRecursive($subRecipe, $subScaleFactor, $result, $visitedRecipeIds);
            }
        }
    }

    private function getIngredientUnitCost(int $ingredientId, ?int $outletId): BigDecimal
    {
        if ($outletId) {
            $cost = IngredientCost::where('ingredient_id', $ingredientId)
                ->where('outlet_id', $outletId)
                ->first();

            if ($cost && $cost->movingAverageCost()->isPositive()) {
                return $cost->movingAverageCost();
            }

            if ($cost && $cost->lastPurchaseCost()->isPositive()) {
                return $cost->lastPurchaseCost();
            }
        }

        // Global fallback
        $globalCost = IngredientCost::where('ingredient_id', $ingredientId)->first();
        if ($globalCost && $globalCost->movingAverageCost()->isPositive()) {
            return $globalCost->movingAverageCost();
        }

        return BigDecimal::of(100); // Nominal minimum fallback IDR 100/unit
    }

    private function ensureLedgerAccountExists(string $code, string $name, AccountKind $kind): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'asset_code' => 'IDR',
                'kind' => $kind->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
