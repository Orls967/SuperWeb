<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Logistics\Application\Actions\ProcessHubInboundScanAction;
use Modules\Logistics\Application\Actions\ProcessHubOutboundAction;
use Modules\Logistics\Application\Actions\ProcessHubSortAction;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\TrackingEvent;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;

class HubOperationsController extends Controller
{
    /**
     * Resolve the active hub for the current user.
     * Hub operators are strictly locked to their assigned hub.
     * Administrators and logistics admins can view/switch between hubs.
     */
    protected function resolveOperatorHub(Request $request): Location
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->isHubOperator()) {
            $assignedHubId = $user->assignedHubId();
            if (! $assignedHubId) {
                abort(403, 'Akun operator belum ditugaskan ke hub mana pun.');
            }

            $hub = Location::find($assignedHubId);
            if (! $hub) {
                abort(404, 'Data hub penugasan tidak ditemukan.');
            }

            return $hub;
        }

        if ($user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher()) {
            $requestedHubId = $request->query('hub_id', $request->input('hub_id'));
            if ($requestedHubId) {
                $hub = Location::find((int) $requestedHubId);
                if ($hub) {
                    return $hub;
                }
            }

            $firstHub = Location::whereIn('type', [LocationType::HUB, LocationType::CFS, LocationType::SEAPORT, LocationType::AIRPORT])->first();
            if (! $firstHub) {
                abort(404, 'Belum ada data hub/lokasi logistik yang tersedia.');
            }

            return $firstHub;
        }

        abort(403, 'Akses ditolak. Halaman khusus operasional hub.');
    }

    /**
     * Display the mobile-friendly Hub Operations dashboard.
     */
    public function index(Request $request): View
    {
        $hub = $this->resolveOperatorHub($request);
        $user = $request->user();

        $allHubs = ($user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher())
            ? Location::whereIn('type', [LocationType::HUB, LocationType::CFS, LocationType::SEAPORT, LocationType::AIRPORT])->orderBy('name')->get()
            : collect([$hub]);

        // Schedules departing from this hub
        $departingSchedules = Schedule::where('origin_location_id', $hub->id)
            ->whereIn('status', [ScheduleStatus::Scheduled, ScheduleStatus::Loading])
            ->with(['destination', 'asset'])
            ->orderBy('etd')
            ->take(15)
            ->get();

        // Recent tracking activity at this hub
        $recentEvents = TrackingEvent::where('location_id', $hub->id)
            ->with(['shipment', 'actor'])
            ->orderByDesc('id')
            ->take(20)
            ->get();

        // Metrics for today at this hub
        $todayInbound = TrackingEvent::where('location_id', $hub->id)
            ->where('event_type', 'HUB_INBOUND')
            ->whereDate('created_at', now()->today())
            ->count();

        $todaySorted = TrackingEvent::where('location_id', $hub->id)
            ->where('event_type', 'SORTED')
            ->whereDate('created_at', now()->today())
            ->count();

        $todayOutbound = TrackingEvent::where('location_id', $hub->id)
            ->where('event_type', 'HUB_OUTBOUND')
            ->whereDate('created_at', now()->today())
            ->count();

        $todayMissorts = TrackingEvent::where('location_id', $hub->id)
            ->where('event_type', 'MISSORT')
            ->whereDate('created_at', now()->today())
            ->count();

        return view('logistics::hub.index', compact(
            'hub',
            'allHubs',
            'departingSchedules',
            'recentEvents',
            'todayInbound',
            'todaySorted',
            'todayOutbound',
            'todayMissorts'
        ));
    }

    /**
     * Inbound Scan: package arrives at the hub.
     * Automatically triggers missort detection if outside route itinerary.
     */
    public function inbound(Request $request, ProcessHubInboundScanAction $action): RedirectResponse
    {
        $request->validate([
            'tracking_number' => 'required|string',
            'hub_id' => 'nullable|integer|exists:lgx_locations,id',
        ]);

        $hub = $this->resolveOperatorHub($request);
        $user = $request->user();

        $trackingNumber = TrackingNumber::normalize($request->input('tracking_number'));
        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (! $shipment) {
            return back()->with('error', "Nomor resi {$trackingNumber} tidak terdaftar di sistem.");
        }

        $result = $action->execute($shipment, $hub, $user);

        if ($result['is_missort']) {
            return back()->with('warning', $result['message']);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * Sort Scan: package is sorted into a bay, bin, or destination gate.
     */
    public function sort(Request $request, ProcessHubSortAction $action): RedirectResponse
    {
        $request->validate([
            'tracking_number' => 'required|string',
            'sort_bay' => 'required|string|max:50',
            'next_location_id' => 'nullable|integer|exists:lgx_locations,id',
            'hub_id' => 'nullable|integer|exists:lgx_locations,id',
        ]);

        $hub = $this->resolveOperatorHub($request);
        $user = $request->user();

        $trackingNumber = TrackingNumber::normalize($request->input('tracking_number'));
        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (! $shipment) {
            return back()->with('error', "Nomor resi {$trackingNumber} tidak terdaftar di sistem.");
        }

        $result = $action->execute(
            shipment: $shipment,
            hub: $hub,
            operator: $user,
            sortBay: (string) $request->input('sort_bay'),
            nextLocationId: $request->filled('next_location_id') ? (int) $request->input('next_location_id') : null
        );

        return back()->with('success', $result['message']);
    }

    /**
     * Outbound Scan: package is loaded onto a departure schedule.
     */
    public function outbound(Request $request, ProcessHubOutboundAction $action): RedirectResponse
    {
        $request->validate([
            'tracking_number' => 'required|string',
            'schedule_id' => 'required|integer|exists:lgx_schedules,id',
            'hub_id' => 'nullable|integer|exists:lgx_locations,id',
        ]);

        $hub = $this->resolveOperatorHub($request);
        $user = $request->user();

        $trackingNumber = TrackingNumber::normalize($request->input('tracking_number'));
        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (! $shipment) {
            return back()->with('error', "Nomor resi {$trackingNumber} tidak terdaftar di sistem.");
        }

        $schedule = Schedule::find((int) $request->input('schedule_id'));
        if (! $schedule) {
            return back()->with('error', 'Jadwal keberangkatan tidak ditemukan.');
        }

        try {
            $result = $action->execute($shipment, $hub, $user, $schedule);

            return back()->with('success', $result['message']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
