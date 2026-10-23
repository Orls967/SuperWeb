<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Services\HotelOperationsService;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;

class HotelController extends Controller
{
    public function index(Request $request): View
    {
        $properties = HotelProperty::latest()->take(10)->get();
        $rooms = HotelRoom::latest()->take(10)->get();
        $reservations = HotelReservation::latest()->take(10)->get();
        $folios = HotelFolio::where('status', 'open')->latest()->take(10)->get();

        return view('hotel::hotel.index', compact('properties', 'rooms', 'reservations', 'folios'));
    }

    public function settleFolio(
        Request $request,
        HotelReservation $reservation,
        HotelOperationsService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'payment_amount' => 'required|integer|min:1000',
        ]);

        $service->checkOutAndSettleFolio((string) $reservation->id, (int) $validated['payment_amount']);

        return redirect()->route('hotel.index')->with('success', "Folio for reservation {$reservation->reservation_number} settled successfully.");
    }
}
