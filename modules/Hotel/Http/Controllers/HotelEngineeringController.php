<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\ResolveServiceRecoveryAction;
use Modules\Hotel\Domain\Adapters\AssetWorkOrderAdapter;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRoom;

final class HotelEngineeringController extends Controller
{
    public function index(): View
    {
        $properties = HotelProperty::all();
        $rooms = HotelRoom::with('property')->take(20)->get();

        return view('hotel::engineering_board', compact('properties', 'rooms'));
    }

    public function createWorkOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'room_id' => 'required|integer',
            'issue_description' => 'required|string',
            'sla_hours' => 'required|integer|min:1',
        ]);

        $wo = AssetWorkOrderAdapter::createRoomMaintenanceWorkOrder(
            roomId: (int) $validated['room_id'],
            issueDescription: $validated['issue_description'],
            slaHours: (int) $validated['sla_hours']
        );

        return back()->with('success', "Work Order {$wo['asset_ref']} berhasil diterbitkan ke modul Asset. SLA: {$wo['sla_deadline']}.");
    }

    public function resolveCompensation(Request $request, ResolveServiceRecoveryAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'reservation_id' => 'required|integer',
            'excess_hours' => 'required|numeric|min:0',
            'issue_description' => 'required|string',
            'idempotency_key' => 'required|string',
        ]);

        $res = $action->execute(
            reservationId: (int) $validated['reservation_id'],
            excessHours: (float) $validated['excess_hours'],
            issueDescription: $validated['issue_description'],
            idempotencyKey: $validated['idempotency_key']
        );

        $freeNightMsg = $res['grants_free_night'] ? ' dan 1 Free Night Certificate diterbitkan.' : '.';

        return back()->with('success', "Kompensasi recovery sebesar Rp {$res['compensation_amount_idr']} dikreditkan ke folio{$freeNightMsg}");
    }
}
