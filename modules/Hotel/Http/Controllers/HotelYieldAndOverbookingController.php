<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\CalculateDynamicBarAndChannelRateAction;
use Modules\Hotel\Application\Actions\ExecuteOverbookingWalkCompensationAction;
use Modules\Hotel\Application\Actions\ReserveRoomWithInventoryNightLockAction;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;

class HotelYieldAndOverbookingController extends Controller
{
    public function reservations(Request $request): View
    {
        $properties = HotelProperty::all();
        $rooms = HotelRoom::where('status', 'available')->get();
        $reservations = HotelReservation::latest()->paginate(15);

        return view('hotel::hotel.room_reservations', compact('properties', 'rooms', 'reservations'));
    }

    public function bookRoom(
        Request $request,
        ReserveRoomWithInventoryNightLockAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'property_id' => 'required|string',
            'room_id' => 'required|string',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'daily_rate_idr' => 'required|integer|min:100000',
        ]);

        $guestId = (string) (auth()->id() ?? 'guest-default-01');

        $result = $action->handle(
            $validated['property_id'],
            $validated['room_id'],
            $guestId,
            $validated['check_in_date'],
            $validated['check_out_date'],
            (int) $validated['daily_rate_idr']
        );

        return redirect()->route('hotel.pms.reservations')
            ->with('success', "Reservation {$result['reservation']->reservation_number} created for {$result['nights']} nights (Total: IDR ".number_format($result['total_room_charge_idr']).').');
    }

    public function yieldRates(Request $request): View
    {
        $properties = HotelProperty::all();

        return view('hotel::hotel.dynamic_yield_rates', compact('properties'));
    }

    public function calcRate(
        Request $request,
        CalculateDynamicBarAndChannelRateAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'property_id' => 'required|string',
            'room_type' => 'required|string|max:32',
            'base_cost_idr' => 'required|integer|min:100000',
            'occupancy_pct' => 'required|numeric|min:0|max:100',
            'channel' => 'nullable|string|max:32',
        ]);

        $result = $action->handle(
            $validated['property_id'],
            $validated['room_type'],
            (int) $validated['base_cost_idr'],
            (float) $validated['occupancy_pct'],
            $validated['channel'] ?? 'DIRECT'
        );

        return redirect()->route('hotel.pms.yield-rates')
            ->with('success', 'Yield Calculated: BAR IDR '.number_format($result['bar_rate_idr'])." | Channel ({$result['channel']}) IDR ".number_format($result['channel_rate_idr']).' (Floor: IDR '.number_format($result['floor_rate_idr']).').');
    }

    public function overbookingWalk(Request $request): View
    {
        $activeReservations = HotelReservation::whereIn('status', ['confirmed', 'checked_in'])->get();

        return view('hotel::hotel.overbooking_walk', compact('activeReservations'));
    }

    public function executeWalk(
        Request $request,
        ExecuteOverbookingWalkCompensationAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'reservation_id' => 'required|string',
            'alternate_property_name' => 'required|string|max:128',
            'transport_allowance_idr' => 'nullable|integer|min:0',
            'goodwill_credit_idr' => 'nullable|integer|min:0',
        ]);

        $result = $action->handle(
            $validated['reservation_id'],
            $validated['alternate_property_name'],
            (int) ($validated['transport_allowance_idr'] ?? 350000),
            (int) ($validated['goodwill_credit_idr'] ?? 1000000)
        );

        return redirect()->route('hotel.pms.overbooking')
            ->with('success', "Overbooked Guest Walk Relocated to {$result['alternate_property_name']}. Total compensation: IDR ".number_format($result['total_compensation_idr']).'.');
    }
}
