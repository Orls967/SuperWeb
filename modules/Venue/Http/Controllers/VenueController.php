<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Venue\Application\Services\VenueOperationsService;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;

class VenueController extends Controller
{
    public function index(Request $request): View
    {
        $venues = EntertainmentVenue::with('zones')->latest()->take(5)->get();
        $events = EntertainmentEvent::with(['venue', 'tickets'])->latest()->take(10)->get();
        $tickets = EntertainmentTicket::with('event')->latest()->take(10)->get();
        $zones = EntertainmentZone::latest()->take(10)->get();

        return view('venue::venue.index', compact('venues', 'events', 'tickets', 'zones'));
    }

    public function scanGate(
        Request $request,
        EntertainmentTicket $ticket,
        VenueOperationsService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'zone_id' => 'required|integer',
            'patron_age' => 'required|integer|min:1',
        ]);

        $zone = EntertainmentZone::findOrFail($validated['zone_id']);

        $service->scanGateAccess($ticket, $zone, (int) $validated['patron_age']);

        return redirect()->route('venue.index')->with('success', "Ticket {$ticket->ticket_number} validated and checked in to {$zone->name}.");
    }
}
