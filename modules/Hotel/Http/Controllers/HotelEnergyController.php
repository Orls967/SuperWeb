<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\RunSetbackScanAction;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRoomEnergyLog;

final class HotelEnergyController extends Controller
{
    public function index(Request $request): View
    {
        $properties = HotelProperty::all();
        $selectedPropertyId = (int) $request->input('property_id', $properties->first()?->id ?? 1);

        $energyLogs = HotelRoomEnergyLog::where('property_id', $selectedPropertyId)
            ->orderByDesc('id')
            ->take(30)
            ->get();

        $totalSavedWh = (int) HotelRoomEnergyLog::where('property_id', $selectedPropertyId)->sum('energy_saved_wh');

        return view('hotel::energy_dashboard', compact('properties', 'selectedPropertyId', 'energyLogs', 'totalSavedWh'));
    }

    public function triggerScan(Request $request, RunSetbackScanAction $action): RedirectResponse
    {
        $propertyId = (int) $request->input('property_id', 1);
        $res = $action->execute($propertyId);

        return back()->with('success', "Pemindaian sensor selesai. {$res['setback_rooms']} kamar dalam mode setback. Penghematan: {$res['total_saved_wh']} Wh.");
    }
}
