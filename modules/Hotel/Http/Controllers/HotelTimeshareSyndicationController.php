<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\DistributeTimeshareRentalYieldAction;
use Modules\Hotel\Application\Actions\ScheduleTimeshareUsageAction;
use Modules\Hotel\Domain\Models\TimeshareUnit;

/**
 * Controller: Tata Kelola Unit Villa Timeshare & Pembagian Hasil Sewa.
 */
final class HotelTimeshareSyndicationController extends Controller
{
    public function index(): View
    {
        $units = TimeshareUnit::with('investors')->get();

        return view('hotel::timeshare_syndication_portal', [
            'units' => $units,
        ]);
    }

    public function schedule(
        Request $request,
        string $unitId,
        ScheduleTimeshareUsageAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'owner_id' => 'required|string',
            'fractions_owned' => 'required|integer|min:1|max:52',
            'requested_owner_weeks' => 'required|array',
            'calendar_year' => 'required|integer|min:2026',
        ]);

        $cert = $action->execute(
            unitId: $unitId,
            ownerId: $validated['owner_id'],
            fractionsOwned: (int) $validated['fractions_owned'],
            requestedOwnerWeeks: (array) $validated['requested_owner_weeks'],
            calendarYear: (int) $validated['calendar_year']
        );

        return response()->json([
            'status' => 'success',
            'data' => $cert,
        ]);
    }

    public function distribute(
        Request $request,
        string $unitId,
        DistributeTimeshareRentalYieldAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'gross_rental_income_idr' => 'required|integer|min:1',
        ]);

        $res = $action->execute(
            unitId: $unitId,
            grossRentalIncomeIdr: (int) $validated['gross_rental_income_idr']
        );

        return response()->json([
            'status' => 'success',
            'data' => $res,
        ]);
    }
}
