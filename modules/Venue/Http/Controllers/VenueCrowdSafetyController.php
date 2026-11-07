<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Venue\Application\Actions\ExecuteCrowdCrushEmergencyAction;
use Modules\Venue\Application\Services\CrowdHeatmapStreamingService;
use Modules\Venue\Application\Services\TurnstileHardwareControllerService;
use Modules\Venue\Application\Services\VenueCrowdSafetyAuditService;
use Modules\Venue\Domain\Models\CrowdCrushIncident;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;

class VenueCrowdSafetyController extends Controller
{
    public function __construct(
        private readonly CrowdHeatmapStreamingService $heatmapService,
        private readonly TurnstileHardwareControllerService $turnstileService,
        private readonly VenueCrowdSafetyAuditService $auditService
    ) {}

    public function dashboard(Request $request): View
    {
        $venue = EntertainmentVenue::first() ?? EntertainmentVenue::create([
            'venue_code' => 'VEN-BALI-ARENA',
            'name' => 'Colosseum Ultra Arena',
            'venue_type' => 'FESTIVAL_GROUNDS',
            'city' => 'Badung',
            'max_legal_capacity' => 15000,
            'min_age_requirement' => 18,
        ]);

        $zones = EntertainmentZone::where('venue_id', $venue->id)->get();
        $cells = $this->heatmapService->getLiveHeatmapCells($venue->id);
        $svgMarkup = $this->heatmapService->renderSvgHeatmapMarkup($venue->id);
        $compliance = $this->auditService->auditZoneCapacityCompliance();
        $activeIncidents = CrowdCrushIncident::whereNull('resolved_at')->with(['zone', 'event'])->latest()->get();

        return view('venue::crowd.dashboard', [
            'venue' => $venue,
            'zones' => $zones,
            'cells' => $cells,
            'svgMarkup' => $svgMarkup,
            'compliance' => $compliance,
            'activeIncidents' => $activeIncidents,
        ]);
    }

    public function heatmapJson(Request $request): JsonResponse
    {
        $venueId = (int) $request->input('venue_id', 1);
        $cells = $this->heatmapService->getLiveHeatmapCells($venueId);

        return response()->json([
            'status' => 'OK',
            'cells' => $cells->map(fn ($c) => $c->toArray()),
            'timestamp' => time(),
        ]);
    }

    public function triggerLockdown(Request $request, EntertainmentZone $zone): RedirectResponse
    {
        $zone->crowd_lockdown_active = true;
        $zone->save();

        return redirect()->back()->with('success', "Lockdown zona {$zone->name} berhasil diaktifkan.");
    }

    public function releaseLockdown(Request $request, EntertainmentZone $zone): RedirectResponse
    {
        $zone->crowd_lockdown_active = false;
        $zone->save();

        return redirect()->back()->with('success', "Lockdown zona {$zone->name} telah dilepaskan.");
    }

    public function triggerEmergency(
        Request $request,
        ExecuteCrowdCrushEmergencyAction $action
    ): RedirectResponse {
        $eventId = (int) $request->input('event_id', 1);
        $zoneId = (int) $request->input('zone_id', 1);
        $notes = (string) $request->input('operator_notes', 'Pemicu darurat operator gerbang');

        $action->execute(
            eventId: $eventId,
            zoneId: $zoneId,
            operatorUserId: (int) auth()->id(),
            operatorNotes: $notes
        );

        return redirect()->back()->with('warning', 'PROTOKOL EVAKUASI DARURAT DIAKTIFKAN: Turnstile terkunci, pintu darurat dibuka.');
    }

    public function resolveEmergency(
        Request $request,
        CrowdCrushIncident $incident,
        ExecuteCrowdCrushEmergencyAction $action
    ): RedirectResponse {
        $action->resolveIncident(
            incident: $incident,
            operatorUserId: (int) auth()->id(),
            resolutionNotes: (string) $request->input('resolution_notes', 'Situasi kembali kondusif')
        );

        return redirect()->back()->with('success', "Insiden {$incident->incident_code} berhasil diselesaikan.");
    }
}
