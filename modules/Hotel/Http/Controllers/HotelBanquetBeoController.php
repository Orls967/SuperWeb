<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\GenerateBanquetBeoProductionSheetAction;
use Modules\Hotel\Domain\Models\HtlBanquetOrder;
use Modules\Hotel\Domain\Models\MiceContract;

final class HotelBanquetBeoController extends Controller
{
    public function index(): View
    {
        $orders = HtlBanquetOrder::with('contract')->latest()->get();

        return view('hotel::banquet_beo_production_sheet', [
            'orders' => $orders,
        ]);
    }

    public function generate(Request $request, GenerateBanquetBeoProductionSheetAction $action): JsonResponse
    {
        $validated = $request->validate([
            'contract_id' => 'required|exists:htl_mice_contracts,id',
            'title' => 'required|string|max:255',
            'meal_period' => 'required|string|in:BREAKFAST,LUNCH,DINNER,COCKTAIL',
            'guaranteed_pax' => 'required|integer|min:1',
            'event_date' => 'required|date',
            'recipe_components' => 'required|array',
            'safety_buffer_ratio' => 'nullable|numeric|min:1.0|max:1.5',
            'event_duration_hours' => 'nullable|numeric|min:1.0|max:12.0',
            'hourly_labor_rate_idr' => 'nullable|integer|min:10000',
        ]);

        $contract = MiceContract::findOrFail($validated['contract_id']);

        $result = $action->execute(
            contract: $contract,
            title: $validated['title'],
            mealPeriod: $validated['meal_period'],
            guaranteedPax: (int) $validated['guaranteed_pax'],
            eventDate: $validated['event_date'],
            recipeComponents: $validated['recipe_components'],
            safetyBufferRatio: isset($validated['safety_buffer_ratio']) ? (float) $validated['safety_buffer_ratio'] : 1.05,
            eventDurationHours: isset($validated['event_duration_hours']) ? (float) $validated['event_duration_hours'] : 4.0,
            hourlyLaborRateIdr: isset($validated['hourly_labor_rate_idr']) ? (int) $validated['hourly_labor_rate_idr'] : 50000
        );

        return response()->json([
            'success' => true,
            'beo_code' => $result['order']->beo_code,
            'prepared_pax' => $result['certificate']->preparedPax,
            'total_food_cogs_idr' => $result['certificate']->totalFoodCogsIdr,
            'total_bev_cogs_idr' => $result['certificate']->totalBeverageCogsIdr,
            'total_labor_cost_idr' => $result['certificate']->totalLaborCostIdr,
            'total_direct_cost_idr' => $result['certificate']->totalDirectCostIdr,
            'signature_hash' => $result['certificate']->signatureHash,
        ]);
    }
}
