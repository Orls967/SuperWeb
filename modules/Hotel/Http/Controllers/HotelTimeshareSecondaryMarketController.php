<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Domain\Models\HtlTimeshareSchedule;
use Modules\Hotel\Domain\Models\TimeshareUnit;
use Modules\Hotel\Domain\Services\HotelTimeshareSecondaryExchangePool;

class HotelTimeshareSecondaryMarketController
{
    public function __construct(
        private readonly HotelTimeshareSecondaryExchangePool $exchangePool
    ) {}

    public function index(Request $request): View
    {
        $units = TimeshareUnit::all();
        $schedules = HtlTimeshareSchedule::with('unit')->where('status', 'OWNER_OCCUPIED')->get();

        return view('hotel::hotel_timeshare_secondary_market', [
            'units' => $units,
            'schedules' => $schedules,
        ]);
    }

    public function executeSwap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'requester_schedule_id' => ['required', 'uuid'],
            'target_schedule_id' => ['required', 'uuid'],
            'balancing_rate_idr' => ['nullable', 'integer', 'min:0'],
        ]);

        $result = $this->exchangePool->executeScheduleSwap(
            requesterScheduleId: $validated['requester_schedule_id'],
            targetScheduleId: $validated['target_schedule_id'],
            standardBalancingRateIdr: (int) ($validated['balancing_rate_idr'] ?? 500000)
        );

        return response()->json([
            'success' => true,
            'message' => 'Pertukaran hak sewa minggu vila fraksional berhasil dieksekusi.',
            'swap_details' => $result,
        ]);
    }
}
