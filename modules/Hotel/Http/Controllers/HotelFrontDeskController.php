<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\CheckInWithDigitalKeyAndApoaAction;
use Modules\Hotel\Application\Actions\RelocateOverbookingWalkSagaAction;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;

final class HotelFrontDeskController extends Controller
{
    public function index(Request $request): View
    {
        $properties = HotelProperty::all();
        $selectedPropertyId = (int) $request->input('property_id', $properties->first()?->id ?? 1);

        $today = CarbonImmutable::now()->format('Y-m-d');

        $arrivals = HotelReservation::where('property_id', $selectedPropertyId)
            ->where('check_in_date', $today)
            ->whereIn('status', ['confirmed', 'reconfirmed'])
            ->get();

        $departures = HotelReservation::where('property_id', $selectedPropertyId)
            ->where('check_out_date', $today)
            ->where('status', 'checked_in')
            ->get();

        $inHouse = HotelReservation::where('property_id', $selectedPropertyId)
            ->where('status', 'checked_in')
            ->get();

        $rooms = HotelRoom::where('property_id', $selectedPropertyId)->get();

        return view('hotel::front_desk', compact('properties', 'selectedPropertyId', 'arrivals', 'departures', 'inHouse', 'rooms'));
    }

    public function checkIn(Request $request, CheckInWithDigitalKeyAndApoaAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'reservation_id' => 'required|integer',
            'room_id' => 'required|integer',
            'guest_nationality' => 'nullable|string|max:3',
            'passport_number' => 'nullable|string|max:32',
            'bali_pwa_paid' => 'nullable|boolean',
        ]);

        $res = $action->execute(
            reservationId: (int) $validated['reservation_id'],
            roomId: (int) $validated['room_id'],
            guestNationality: $validated['guest_nationality'] ?? 'ID',
            passportNumber: $validated['passport_number'] ?? null,
            isBaliPwaPaid: (bool) ($validated['bali_pwa_paid'] ?? false)
        );

        return back()->with('success', "Check-in berhasil untuk kamar {$res['room_number']}. Kunci digital aktif.");
    }

    public function relocateWalk(Request $request, RelocateOverbookingWalkSagaAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'reservation_id' => 'required|integer',
            'target_property_id' => 'required|integer',
            'idempotency_key' => 'required|string',
        ]);

        $res = $action->execute(
            reservationId: (int) $validated['reservation_id'],
            targetNeighborPropertyId: (int) $validated['target_property_id'],
            idempotencyKey: $validated['idempotency_key']
        );

        return back()->with('success', "Tamu berhasil direlokasi (Walk). Kompensasi voucher: Rp {$res['compensation_voucher_idr']}.");
    }
}
