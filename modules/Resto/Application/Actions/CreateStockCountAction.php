<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Resto\Domain\Enums\StockCountStatus;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\IngredientCost;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\StockCount;
use Modules\Resto\Domain\Models\StockCountLine;

class CreateStockCountAction
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    /**
     * @param  array<int, float|string>  $countedQuantities  [ingredient_id => counted_qty]
     */
    public function handle(
        int $outletId,
        array $countedQuantities,
        User $counter,
        ?string $notes = null
    ): StockCount {
        $outlet = Outlet::findOrFail($outletId);

        return DB::transaction(function () use ($outlet, $countedQuantities, $counter, $notes) {
            $stockCount = StockCount::create([
                'uuid' => (string) Str::uuid(),
                'outlet_id' => $outlet->id,
                'date' => now()->toDateString(),
                'status' => StockCountStatus::SUBMITTED,
                'counted_by' => $counter->id,
                'notes' => $notes,
            ]);

            // All ingredients
            $ingredients = Ingredient::all();

            foreach ($ingredients as $ing) {
                $systemQtyStr = $this->inventoryService->availableIngredient($ing->id, $outlet->id);
                $systemQty = BigDecimal::of((string) $systemQtyStr);

                $countedQty = isset($countedQuantities[$ing->id])
                    ? BigDecimal::of((string) $countedQuantities[$ing->id])
                    : $systemQty;

                $variance = $countedQty->minus($systemQty);

                $costRecord = IngredientCost::where('ingredient_id', $ing->id)
                    ->where('outlet_id', $outlet->id)
                    ->first();
                $unitCost = $costRecord ? BigDecimal::of((string) $costRecord->moving_avg_cost_per_base_unit) : BigDecimal::zero();
                $varianceValue = $variance->multipliedBy($unitCost)->toScale(0, RoundingMode::HalfUp)->toInt();

                StockCountLine::create([
                    'count_id' => $stockCount->id,
                    'ingredient_id' => $ing->id,
                    'system_qty' => $systemQty->__toString(),
                    'counted_qty' => $countedQty->__toString(),
                    'variance' => $variance->__toString(),
                    'variance_value' => $varianceValue,
                ]);
            }

            return $stockCount->load(['lines.ingredient', 'outlet', 'counter']);
        });
    }
}
