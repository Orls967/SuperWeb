<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Domain\Models\HotelDigitalKey;
use Modules\Hotel\Domain\Models\HotelReservation;

final class GuestStayPortalController extends Controller
{
    public function stayDetails(Request $request): View
    {
        $user = $request->user();
        $reservation = HotelReservation::where('guest_user_id', (string) $user?->id)
            ->orWhere('guest_email', (string) $user?->email)
            ->latest()
            ->first();

        $digitalKey = null;
        if ($reservation !== null) {
            $digitalKey = HotelDigitalKey::where('reservation_id', (string) $reservation->id)
                ->where('guest_user_id', (string) $user?->id)
                ->latest()
                ->first();
        }

        return view('hotel::guest_stay_portal', compact('reservation', 'digitalKey'));
    }
}
