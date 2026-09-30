<?php

declare(strict_types=1);

namespace Modules\Mall\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mall\Application\Queries\OccupancyQuery;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\Unit;

class SitePlanController extends Controller
{
    public function index(Request $request, OccupancyQuery $occupancyQuery): View
    {
        $propertyId = $request->input('property_id') ? (int) $request->input('property_id') : null;
        $properties = Property::where('is_active', true)->get();

        $selectedProperty = $propertyId ? Property::find($propertyId) : $properties->first();
        $targetPropertyId = $selectedProperty?->id;

        $floor = $request->input('floor') ?: 'GF';

        $units = Unit::with(['zone', 'activeLease.tenant'])
            ->when($targetPropertyId, fn ($q) => $q->where('property_id', $targetPropertyId))
            ->where('floor', $floor)
            ->get();

        $occupancyData = $occupancyQuery->execute($targetPropertyId);

        // Ambil daftar seluruh lantai yang ada pada unit mall ini
        $availableFloors = Unit::when($targetPropertyId, fn ($q) => $q->where('property_id', $targetPropertyId))
            ->select('floor')
            ->distinct()
            ->pluck('floor')
            ->all();

        if (empty($availableFloors)) {
            $availableFloors = ['LG', 'GF', 'L1', 'L2', 'L3'];
        }

        return view('mall::site_plan.index', [
            'properties' => $properties,
            'selectedProperty' => $selectedProperty,
            'selectedFloor' => $floor,
            'availableFloors' => $availableFloors,
            'units' => $units,
            'occupancy' => $occupancyData,
        ]);
    }
}
