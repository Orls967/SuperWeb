<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Hotel\Application\Actions\ExecuteHotelNightAuditAction;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\Domain\Models\HotelDailyRevenueSnapshot;
use Modules\Hotel\Domain\Models\HotelProperty;

class HotelNightAuditUiController extends Controller
{
    public function index(Request $request, HotelBusinessDateService $service)
    {
        $property = HotelProperty::firstOrFail();
        $record = $service->getBusinessDateRecord($property->id);
        $recentSnapshots = HotelDailyRevenueSnapshot::where('property_id', $property->id)
            ->orderByDesc('business_date')
            ->limit(7)
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'business_date' => $record,
                'snapshots' => $recentSnapshots,
            ]);
        }

        return view('hotel::hotel.night_audit_dashboard', compact('property', 'record', 'recentSnapshots'));
    }

    public function run(Request $request, ExecuteHotelNightAuditAction $action)
    {
        $property = HotelProperty::firstOrFail();
        $snapshot = $action->execute($property->id);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'snapshot' => $snapshot,
            ]);
        }

        return redirect()->route('hotel.night-audit.index')->with('success', 'Night audit berhasil diselesaikan.');
    }
}
