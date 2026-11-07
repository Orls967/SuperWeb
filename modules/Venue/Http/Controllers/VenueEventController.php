<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Venue\Application\Actions\CloseEntertainmentEventAction;
use Modules\Venue\Domain\Algorithms\TierCountdownPricer;
use Modules\Venue\Domain\Enums\EventStatus;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;

class VenueEventController extends Controller
{
    public function index(Request $request): View
    {
        $events = EntertainmentEvent::with(['venue', 'tiers', 'permits'])
            ->orderByDesc('doors_open_at')
            ->paginate(15);

        $venues = EntertainmentVenue::all();

        return view('venue::events.index', [
            'events' => $events,
            'venues' => $venues,
        ]);
    }

    public function create(): View
    {
        $venues = EntertainmentVenue::all();

        return view('venue::events.create', [
            'venues' => $venues,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'venue_id' => 'required|exists:entertainment_venues,id',
            'event_code' => 'required|string|unique:entertainment_events,event_code|max:50',
            'title' => 'required|string|max:150',
            'doors_open_at' => 'required|date',
            'doors_close_at' => 'nullable|date|after:doors_open_at',
            'ticket_floor_price_idr' => 'required|integer|min:0',
            'ticket_ceiling_price_idr' => 'required|integer|gte:ticket_floor_price_idr',
            'current_ticket_price_idr' => 'required|integer|gte:ticket_floor_price_idr',
        ]);

        $event = EntertainmentEvent::create([
            ...$validated,
            'status' => EventStatus::DRAFT->value,
        ]);

        return redirect()->route('venue.events.show', $event)->with('success', 'Event berhasil didaftarkan');
    }

    public function show(EntertainmentEvent $event): View
    {
        $event->load(['venue', 'tiers', 'permits', 'tickets']);

        return view('venue::events.show', [
            'event' => $event,
        ]);
    }

    public function closeEvent(EntertainmentEvent $event, CloseEntertainmentEventAction $action): RedirectResponse
    {
        $action->execute($event);

        return redirect()->route('venue.events.show', $event)->with('success', "Event {$event->title} berhasil ditutup dan pendapatan tiket diakui.");
    }

    public function simulator(EntertainmentEvent $event, Request $request): View
    {
        $demandBps = (int) $request->input('demand_bps', 6000);
        $rainProbBps = (int) $request->input('rain_probability_bps', 1000);
        $daysUntil = (int) $request->input('days_until_event', 15);
        $basePrice = (int) $request->input('base_price_idr', $event->ticket_floor_price_idr ?: 300000);

        $calculation = TierCountdownPricer::calculate(
            basePriceIdr: $basePrice,
            daysUntilEvent: $daysUntil,
            soldRatioBps: $demandBps,
            rainProbabilityBps: $rainProbBps,
            floorPriceIdr: (int) $event->ticket_floor_price_idr,
            ceilingPriceIdr: (int) $event->ticket_ceiling_price_idr
        );

        return view('venue::events.pricing-simulator', [
            'event' => $event,
            'demandBps' => $demandBps,
            'rainProbBps' => $rainProbBps,
            'daysUntil' => $daysUntil,
            'basePrice' => $basePrice,
            'calculation' => $calculation,
        ]);
    }
}
