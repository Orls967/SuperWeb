<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Venue\Application\Actions\IssueCrowdPermitAction;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\VenuePermit;

class VenuePermitController extends Controller
{
    public function index(Request $request): View
    {
        $permits = VenuePermit::with('event')
            ->orderByDesc('issued_at')
            ->paginate(15);

        $events = EntertainmentEvent::where('status', '!=', 'cancelled')->get();

        return view('venue::permits.index', [
            'permits' => $permits,
            'events' => $events,
        ]);
    }

    public function store(
        Request $request,
        IssueCrowdPermitAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'event_id' => 'required|exists:entertainment_events,id',
            'permit_type' => 'required|string|max:50',
            'issuing_authority' => 'required|string|max:100',
            'authorized_capacity' => 'required|integer|min:1',
            'approved_noise_curfew' => 'nullable|string|max:10',
            'expires_at' => 'required|date|after:today',
        ]);

        $event = EntertainmentEvent::findOrFail($validated['event_id']);

        $permit = $action->execute(
            event: $event,
            permitType: $validated['permit_type'],
            issuingAuthority: $validated['issuing_authority'],
            authorizedCapacity: (int) $validated['authorized_capacity'],
            approvedNoiseCurfew: $validated['approved_noise_curfew'] ?? '02:00',
            expiresAt: $validated['expires_at'],
            termsPayload: [
                'fire_safety_inspected' => true,
                'medical_post_ready' => true,
                'emergency_exits_unobstructed' => true,
            ]
        );

        return redirect()->route('venue.permits.index')
            ->with('success', "Izin keramaian {$permit->permit_number} berhasil diterbitkan dengan gapless hash-chain.");
    }
}

