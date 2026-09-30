<?php

declare(strict_types=1);

namespace Modules\Resto\Http\Controllers;

use App\Http\Controllers\Controller;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Resto\Application\Actions\CookBatchAction;
use Modules\Resto\Application\Actions\DiscardTrayAction;
use Modules\Resto\Application\Actions\RecirculateTrayAction;
use Modules\Resto\Domain\Enums\RecipeLineType;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Exceptions\InvalidTrayOperationException;
use Modules\Resto\Domain\Exceptions\ShortageException;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\ProductionBatch;
use Modules\Resto\Domain\Models\Recipe;

class KitchenController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeKitchenAccess($user);

        $outlets = Outlet::where('is_active', true)->orderBy('name')->get();

        $selectedOutletId = $this->resolveOutletId($request, $user, $outlets);
        $currentOutlet = Outlet::findOrFail($selectedOutletId);

        $todayBatches = ProductionBatch::where('outlet_id', $selectedOutletId)
            ->whereDate('created_at', today())
            ->with(['recipe', 'menuItem', 'producedBy'])
            ->latest()
            ->get();

        $displayTrays = DisplayTray::where('outlet_id', $selectedOutletId)
            ->whereIn('status', [TrayStatus::ON_DISPLAY, TrayStatus::IN_SERVICE, TrayStatus::RETURNED])
            ->with(['menuItem', 'batch'])
            ->latest('placed_at')
            ->get();

        $todayWastes = DisplayTray::where('outlet_id', $selectedOutletId)
            ->where('status', TrayStatus::DISCARDED)
            ->whereDate('updated_at', today())
            ->with(['menuItem', 'batch'])
            ->latest('updated_at')
            ->get();

        $todayWasteTotal = $todayWastes->sum(fn (DisplayTray $t) => (int) $t->cost_per_portion * (int) ($t->portions_remaining ?: 1));

        $recipes = Recipe::where('is_active', true)
            ->with(['menuItem', 'lines.ingredient'])
            ->orderBy('sub_recipe_name')
            ->get();

        return view('resto::kitchen.index', [
            'outlets' => $outlets,
            'currentOutlet' => $currentOutlet,
            'todayBatches' => $todayBatches,
            'displayTrays' => $displayTrays,
            'todayWastes' => $todayWastes,
            'todayWasteTotal' => $todayWasteTotal,
            'recipes' => $recipes,
        ]);
    }

    public function simulate(Request $request, InventoryService $inventoryService): JsonResponse
    {
        $user = $request->user();
        $this->authorizeKitchenAccess($user);

        $validated = $request->validate([
            'outlet_id' => 'required|exists:resto_outlets,id',
            'recipe_id' => 'required|exists:resto_recipes,id',
            'portions' => 'required|integer|min:1',
        ]);

        $outletId = (int) $validated['outlet_id'];
        $this->assertStaffBelongsToOutlet($user, $outletId);

        $recipe = Recipe::with('lines.ingredient', 'lines.subRecipe.lines')->findOrFail($validated['recipe_id']);
        $portions = (int) $validated['portions'];

        $expected = $recipe->expectedPortions();
        $scaleFactor = $expected->isPositive()
            ? BigDecimal::of($portions)->dividedBy($expected, 6, RoundingMode::HalfUp)
            : BigDecimal::one();

        /** @var array<int, array{ingredient: Ingredient, qty: BigDecimal}> $requiredIngredients */
        $requiredIngredients = [];
        $this->flattenIngredients($recipe, $scaleFactor, $requiredIngredients);

        $neededDetails = [];
        $canCook = true;
        $maxPossibleList = [];

        foreach ($requiredIngredients as $ingId => $data) {
            $neededQty = $data['qty'];
            $availableStr = $inventoryService->availableIngredient($ingId, $outletId);
            $availableQty = BigDecimal::of($availableStr);

            $isShortage = $availableQty->isLessThan($neededQty);
            if ($isShortage) {
                $canCook = false;
            }

            $qtyPerPortion = $neededQty->dividedBy(BigDecimal::of($portions), 6, RoundingMode::HalfUp);
            if ($qtyPerPortion->isPositive()) {
                $maxPossible = (int) floor((float) $availableQty->dividedBy($qtyPerPortion, 6, RoundingMode::Down)->__toString());
                $maxPossibleList[] = max(0, $maxPossible);
            }

            $neededDetails[] = [
                'ingredient_id' => $ingId,
                'name' => $data['ingredient']->name,
                'category' => $data['ingredient']->category->label(),
                'base_unit' => $data['ingredient']->base_unit,
                'needed' => (float) $neededQty->toScale(2, RoundingMode::HalfUp)->__toString(),
                'available' => (float) $availableQty->toScale(2, RoundingMode::HalfUp)->__toString(),
                'is_shortage' => $isShortage,
                'shortage_amount' => $isShortage ? (float) $neededQty->minus($availableQty)->toScale(2, RoundingMode::HalfUp)->__toString() : 0,
            ];
        }

        $suggestedPortions = ! empty($maxPossibleList) ? min($maxPossibleList) : 0;

        return response()->json([
            'can_cook' => $canCook,
            'portions' => $portions,
            'suggested_portions' => $suggestedPortions,
            'ingredients' => $neededDetails,
        ]);
    }

    public function cook(Request $request, CookBatchAction $action): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeKitchenAccess($user);

        $validated = $request->validate([
            'outlet_id' => 'required|exists:resto_outlets,id',
            'recipe_id' => 'required|exists:resto_recipes,id',
            'planned_portions' => 'required|integer|min:1',
            'actual_portions' => 'nullable|integer|min:1',
            'note' => 'nullable|string|max:500',
            'put_on_display' => 'nullable|boolean',
        ]);

        $outletId = (int) $validated['outlet_id'];
        $this->assertStaffBelongsToOutlet($user, $outletId);

        try {
            $batch = $action->handle(
                outletId: $outletId,
                recipeId: (int) $validated['recipe_id'],
                plannedPortions: (int) $validated['planned_portions'],
                actualPortions: isset($validated['actual_portions']) ? (int) $validated['actual_portions'] : null,
                producedBy: $user->id,
                note: $validated['note'] ?? null,
                putOnDisplay: (bool) ($validated['put_on_display'] ?? true)
            );

            return redirect()->route('resto.kitchen.index', ['outlet_id' => $outletId])
                ->with('success', "Batch {$batch->batch_no} berhasil dimasak ({$batch->actual_portions} porsi) dan tercatat di sistem.");
        } catch (ShortageException $e) {
            $msg = $e->getMessage();
            if ($e->suggestedMaxPortions > 0) {
                $msg .= " Saran: Stok saat ini mencukupi untuk maksimal {$e->suggestedMaxPortions} porsi.";
            }

            return redirect()->back()
                ->withInput()
                ->with('error', $msg);
        }
    }

    public function discardTray(Request $request, DisplayTray $tray, DiscardTrayAction $action): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeKitchenAccess($user);
        $this->assertStaffBelongsToOutlet($user, (int) $tray->outlet_id);

        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:255',
        ]);

        $action->handle($tray, $validated['reason'], $user->id);

        return redirect()->route('resto.kitchen.index', ['outlet_id' => $tray->outlet_id])
            ->with('success', "Piring etalase #{$tray->id} ({$tray->menuItem->name}) dialihkan ke waste.");
    }

    public function recirculateTray(Request $request, DisplayTray $tray, RecirculateTrayAction $action): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeKitchenAccess($user);
        $this->assertStaffBelongsToOutlet($user, (int) $tray->outlet_id);

        try {
            $action->handle($tray, $user->id);

            return redirect()->route('resto.kitchen.index', ['outlet_id' => $tray->outlet_id])
                ->with('success', "Piring hidang #{$tray->id} ({$tray->menuItem->name}) berhasil dikembalikan ke etalase (Resirkulasi ke-{$tray->recirculation_count}).");
        } catch (InvalidTrayOperationException $e) {
            return redirect()->route('resto.kitchen.index', ['outlet_id' => $tray->outlet_id])
                ->with('error', $e->getMessage());
        }
    }

    private function authorizeKitchenAccess($user): void
    {
        if (! $user->isAdmin() && ! $user->isOutletManager() && ! $user->isKitchen()) {
            abort(403, 'Akses khusus staf dapur dan outlet manager.');
        }
    }

    private function assertStaffBelongsToOutlet($user, int $outletId): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $assignedOutletId = $user->assignedOutletId();
        if ($assignedOutletId !== null && $assignedOutletId !== $outletId) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengelola dapur outlet ini.');
        }
    }

    private function resolveOutletId(Request $request, $user, $outlets): int
    {
        if (! $user->isAdmin()) {
            $assignedId = $user->assignedOutletId();
            if ($assignedId !== null) {
                if ($request->has('outlet_id') && (int) $request->query('outlet_id') !== $assignedId) {
                    abort(403, 'Anda tidak diizinkan mengakses data outlet lain.');
                }

                return $assignedId;
            }
        }

        if ($request->has('outlet_id')) {
            $reqId = (int) $request->query('outlet_id');
            if ($outlets->contains('id', $reqId)) {
                return $reqId;
            }
        }

        return (int) ($outlets->first()?->id ?? 1);
    }

    private function flattenIngredients(Recipe $recipe, BigDecimal $scaleFactor, array &$result, array $visited = []): void
    {
        $visited[] = $recipe->id;
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
            } elseif ($line->line_type === RecipeLineType::SUB_RECIPE && $line->sub_recipe_id && ! in_array($line->sub_recipe_id, $visited, true)) {
                $subRecipe = $line->subRecipe ?? Recipe::with('lines.ingredient')->findOrFail($line->sub_recipe_id);
                $subYield = $subRecipe->yieldQuantity();
                $subScale = $subYield->isPositive()
                    ? $effectiveQty->dividedBy($subYield, 6, RoundingMode::HalfUp)
                    : BigDecimal::one();

                $this->flattenIngredients($subRecipe, $subScale, $result, $visited);
            }
        }
    }
}
