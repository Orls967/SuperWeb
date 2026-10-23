<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Venue\Application\Actions\IssueFestivalTicketWithGaplessHashAction;
use Modules\Venue\Application\Actions\ScanTicketGateWithAntiPassbackAndAgeCheckAction;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentZone;

class VenueTicketingAccessController extends Controller
{
    public function ticketing(Request $request): View
    {
        $events = EntertainmentEvent::latest()->get();
        $tickets = EntertainmentTicket::with('event')->latest()->paginate(15);

        return view('venue::venue.festival_ticketing', compact('events', 'tickets'));
    }

    public function issueTicket(
        Request $request,
        IssueFestivalTicketWithGaplessHashAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'event_id' => 'required|integer',
            'price_paid_idr' => 'required|integer|min:1000',
            'ticket_tier' => 'nullable|string|max:32',
        ]);

        $userId = (int) (auth()->id() ?? 1);

        $result = $action->handle(
            (int) $validated['event_id'],
            $userId,
            (int) $validated['price_paid_idr'],
            $validated['ticket_tier'] ?? 'GENERAL_ADMISSION'
        );

        return redirect()->route('venue.ticketing.index')
            ->with('success', "Ticket issued: {$result['ticket_number']} (Hash: ".substr($result['ticket_hash'], 0, 16).'...).');
    }

    public function gateAccess(Request $request): View
    {
        $zones = EntertainmentZone::all();
        $validTickets = EntertainmentTicket::where('status', 'VALID')->get();

        return view('venue::venue.gate_access', compact('zones', 'validTickets'));
    }

    public function scanGate(
        Request $request,
        ScanTicketGateWithAntiPassbackAndAgeCheckAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'ticket_id' => 'required|integer',
            'attendee_age' => 'required|integer|min:1',
            'zone_id' => 'required|integer',
            'gate_code' => 'nullable|string|max:32',
        ]);

        $result = $action->handle(
            (int) $validated['ticket_id'],
            (int) $validated['attendee_age'],
            (int) $validated['zone_id'],
            $validated['gate_code'] ?? 'GATE-MAIN-01'
        );

        if ($result['status'] === 'DENIED') {
            return redirect()->route('venue.ticketing.gate')
                ->with('error', "Entry Denied: {$result['reason']}");
        }

        return redirect()->route('venue.ticketing.gate')
            ->with('success', "Admitted! Zone occupancy: {$result['zone_occupancy']}. {$result['reason']}");
    }
}
