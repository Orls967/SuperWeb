<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Hotel\Domain\Models\HotelReservation;

class HotelGuestPortalController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();

        $reservations = HotelReservation::with(['folio.lines', 'property', 'roomType'])
            ->where('guest_user_id', (string) $userId)
            ->orderByDesc('created_at')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'reservations' => $reservations,
            ]);
        }

        return view('hotel::hotel.portal_guest', compact('reservations'));
    }
}
