<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\ReserveExhibitionTradeShowBoothAction;
use Modules\Hotel\Domain\Models\HtlExhibitionBoothReservation;
use Modules\Hotel\Domain\Models\MiceContract;

final class HotelExhibitionBoothController extends Controller
{
    public function index(): View
    {
        $booths = HtlExhibitionBoothReservation::with('contract')->latest()->get();

        return view('hotel::exhibition_booth_floorplan_designer', [
            'booths' => $booths,
        ]);
    }

    public function reserve(Request $request, ReserveExhibitionTradeShowBoothAction $action): JsonResponse
    {
        $validated = $request->validate([
            'contract_id' => 'required|exists:htl_mice_contracts,id',
            'booth_number' => 'required|string|max:32',
            'booth_type' => 'required|string|in:SHELL_3X3,SHELL_6X3,ISLAND',
            'grid_x' => 'required|integer|min:0',
            'grid_y' => 'required|integer|min:0',
            'width_m' => 'nullable|integer|min:1',
            'length_m' => 'nullable|integer|min:1',
            'electricity_kva' => 'nullable|numeric|min:0',
            'is_dedicated_lan' => 'nullable|boolean',
            'base_rental_idr' => 'nullable|integer|min:0',
        ]);

        $contract = MiceContract::findOrFail($validated['contract_id']);

        $booth = $action->execute(
            contract: $contract,
            boothNumber: $validated['booth_number'],
            boothType: $validated['booth_type'],
            gridX: (int) $validated['grid_x'],
            gridY: (int) $validated['grid_y'],
            widthM: (int) ($validated['width_m'] ?? 3),
            lengthM: (int) ($validated['length_m'] ?? 3),
            electricityKva: isset($validated['electricity_kva']) ? (float) $validated['electricity_kva'] : 0.5,
            isDedicatedLan: (bool) ($validated['is_dedicated_lan'] ?? false),
            baseRentalIdr: (int) ($validated['base_rental_idr'] ?? 15000000)
        );

        return response()->json([
            'success' => true,
            'reservation_code' => $booth->reservation_code,
            'booth_number' => $booth->booth_number,
            'total_charge_idr' => $booth->total_charge_idr,
            'estimated_daily_footfall' => $booth->estimated_daily_footfall,
        ]);
    }
}
