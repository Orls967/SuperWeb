<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Hotel\Domain\Services\HotelCorporateLoyaltyPoolService;

final class HotelCorporateLoyaltyController extends Controller
{
    public function __construct(
        protected HotelCorporateLoyaltyPoolService $poolService
    ) {}

    public function allocate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'corporate_master_pass_id' => 'required|string',
            'employee_pass_id' => 'required|string',
            'points_to_allocate' => 'required|integer|min:100',
            'travel_purpose' => 'nullable|string',
        ]);

        $result = $this->poolService->allocatePointsFromCorporateMasterToEmployee(
            corporateMasterPassId: $validated['corporate_master_pass_id'],
            employeePassId: $validated['employee_pass_id'],
            pointsToAllocate: (int) $validated['points_to_allocate'],
            travelPurpose: $validated['travel_purpose'] ?? 'BUSINESS_TRIP_MICE_DELEGATION'
        );

        return redirect()->back()->with('success', "Alokasi poin korporat berhasil! {$result['allocated_points']} PTS dialihkan ke anggota karyawan.");
    }
}
