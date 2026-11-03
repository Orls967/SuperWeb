<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Hotel\Application\Actions\ReserveRoomAction;
use Modules\Hotel\Application\DTOs\ReserveRoomDTO;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRatePlan;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoomType;
use Modules\Hotel\Http\Requests\ReserveRoomRequest;

class HotelReservationUiController extends Controller
{
    public function index(Request $request)
    {
        $property = HotelProperty::firstOrFail();
        $reservations = HotelReservation::with(['room', 'roomType', 'ratePlan', 'folio'])
            ->where('property_id', $property->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $roomTypes = HotelRoomType::where('property_id', $property->id)->get();
        $ratePlans = HotelRatePlan::where('property_id', $property->id)->get();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'reservations' => $reservations,
                'room_types' => $roomTypes,
                'rate_plans' => $ratePlans,
            ]);
        }

        return view('hotel::hotel.reservations_dashboard', compact('property', 'reservations', 'roomTypes', 'ratePlans'));
    }

    public function store(ReserveRoomRequest $request, ReserveRoomAction $action)
    {
        $validated = $request->validated();

        $dto = new ReserveRoomDTO(
            propertyId: (string) $validated['property_id'],
            roomTypeId: (string) $validated['room_type_id'],
            ratePlanId: (string) $validated['rate_plan_id'],
            guestUserId: (string) $validated['guest_user_id'],
            checkInDate: (string) $validated['check_in_date'],
            checkOutDate: (string) $validated['check_out_date'],
            idempotencyKey: (string) $validated['idempotency_key'],
            roomId: isset($validated['room_id']) ? (string) $validated['room_id'] : null,
            depositAmountIdr: isset($validated['deposit_amount_idr']) ? (int) $validated['deposit_amount_idr'] : null,
        );

        $result = $action->handle($dto);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'reservation' => $result['reservation'],
                'folio' => $result['folio'],
            ], 201);
        }

        return redirect()->route('hotel.reservations.index')->with('success', 'Reservasi berhasil dibuat.');
    }
}
