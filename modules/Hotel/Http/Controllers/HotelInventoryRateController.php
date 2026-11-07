<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\PublishRatePlanAction;
use Modules\Hotel\Domain\Algorithms\OverbookingLimitOptimizer;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRatePlanVersion;
use Modules\Hotel\Domain\Models\HotelRoomType;
use Modules\Hotel\Domain\Services\HotelExternalFactors;

final class HotelInventoryRateController extends Controller
{
    public function inventoryGrid(Request $request): View
    {
        $properties = HotelProperty::all();
        $selectedPropertyId = (int) $request->input('property_id', $properties->first()?->id ?? 1);
        $roomTypes = HotelRoomType::where('property_id', $selectedPropertyId)->get();

        // 30 hari ke depan
        $days = [];
        $start = CarbonImmutable::now();
        for ($i = 0; $i < 30; $i++) {
            $days[] = $start->addDays($i)->format('Y-m-d');
        }

        return view('hotel::inventory_grid', compact('properties', 'selectedPropertyId', 'roomTypes', 'days'));
    }

    public function ratesView(Request $request): View
    {
        $properties = HotelProperty::all();
        $selectedPropertyId = (int) $request->input('property_id', $properties->first()?->id ?? 1);
        $roomTypes = HotelRoomType::where('property_id', $selectedPropertyId)->get();

        $publishedRates = HotelRatePlanVersion::where('property_id', $selectedPropertyId)
            ->orderByDesc('business_date')
            ->paginate(15);

        return view('hotel::rates_view', compact('properties', 'selectedPropertyId', 'roomTypes', 'publishedRates'));
    }

    public function publishRates(Request $request, PublishRatePlanAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'property_id' => 'required|integer',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $res = $action->execute(
            propertyId: (int) $validated['property_id'],
            fromDate: $validated['from_date'],
            toDate: $validated['to_date'],
            approverUserId: $request->user()?->id
        );

        return back()->with('success', "Berhasil menerbitkan {$res['published_records']} tarif BAR dengan versi {$res['version_code']}.");
    }

    public function overbookingSimulator(Request $request): View
    {
        $capacity = (int) $request->input('capacity', 100);
        $revenue = (int) $request->input('revenue', 1000000);
        $walkCost = (int) $request->input('walk_cost', 2500000);
        $noShowDist = HotelExternalFactors::getNoShowDistributionBps();

        $res = OverbookingLimitOptimizer::optimize(
            capacity: $capacity,
            noShowDistributionBps: $noShowDist,
            revenuePerRoomIdr: $revenue,
            walkCostIdr: $walkCost,
            policyMaxPercentBps: 300
        );

        return view('hotel::overbooking_simulator', compact('capacity', 'revenue', 'walkCost', 'res'));
    }
}
