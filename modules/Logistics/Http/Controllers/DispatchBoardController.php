<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Logistics\Application\Actions\AssignScheduleResourcesAction;
use Modules\Logistics\Application\Actions\AssignShipmentToDriverAction;
use Modules\Logistics\Application\Actions\ReleaseScheduleResourcesAction;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Models\DispatchAssignment;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\Truck;
use RuntimeException;

class DispatchBoardController extends Controller
{
    protected function authorizeDispatcher(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher()),
            403,
            'Akses ditolak. Papan dispatch khusus dispatcher dan admin logistik.'
        );
    }

    public function index(Request $request): View
    {
        $this->authorizeDispatcher($request);

        $schedules = Schedule::where('mode', TransportMode::ROAD->value)
            ->whereIn('status', [ScheduleStatus::Scheduled->value, ScheduleStatus::Loading->value])
            ->with(['origin', 'destination', 'driver.user', 'asset'])
            ->orderBy('etd')
            ->limit(50)
            ->get();

        $trucks = Truck::with('vehicle')
            ->whereIn('status', [FleetStatus::AVAILABLE->value, FleetStatus::ASSIGNED->value])
            ->orderBy('plate_number')
            ->get();

        $drivers = Driver::with(['user', 'homeHub'])
            ->where('status', '!=', 'suspended')
            ->orderBy('driver_number')
            ->get();

        $pendingShipments = Shipment::whereIn('status', array_map(fn ($s) => $s->value, AssignShipmentToDriverAction::ASSIGNABLE_STATUSES))
            ->with(['origin', 'destination', 'driver.user'])
            ->orderBy('booked_at')
            ->limit(30)
            ->get();

        $recentAssignments = DispatchAssignment::with(['schedule', 'truck', 'driver.user', 'assigner'])
            ->latest('id')
            ->limit(10)
            ->get();

        return view('logistics::dispatch.index', compact(
            'schedules',
            'trucks',
            'drivers',
            'pendingShipments',
            'recentAssignments'
        ));
    }

    public function assign(Request $request, AssignScheduleResourcesAction $action): RedirectResponse
    {
        $this->authorizeDispatcher($request);

        $data = $request->validate([
            'schedule_id' => 'required|integer|exists:lgx_schedules,id',
            'truck_id' => 'required|integer|exists:lgx_trucks,id',
            'driver_id' => 'required|integer|exists:lgx_drivers,id',
        ]);

        try {
            $assignment = $action->execute(
                Schedule::findOrFail($data['schedule_id']),
                Truck::findOrFail($data['truck_id']),
                Driver::findOrFail($data['driver_id']),
                $request->user()
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Penugasan berhasil: {$assignment->truck->plate_number} dan {$assignment->driver->driver_number} untuk jadwal {$assignment->schedule->schedule_number}.");
    }

    public function release(Request $request, Schedule $schedule, ReleaseScheduleResourcesAction $action): RedirectResponse
    {
        $this->authorizeDispatcher($request);

        try {
            $action->execute($schedule, 'Dilepas oleh '.$request->user()->name);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Penugasan jadwal {$schedule->schedule_number} dilepas.");
    }

    public function assignShipment(Request $request, AssignShipmentToDriverAction $action): RedirectResponse
    {
        $this->authorizeDispatcher($request);

        $data = $request->validate([
            'shipment_id' => 'required|integer|exists:lgx_shipments,id',
            'driver_id' => 'required|integer|exists:lgx_drivers,id',
        ]);

        try {
            $shipment = $action->execute(
                Shipment::findOrFail($data['shipment_id']),
                Driver::findOrFail($data['driver_id']),
                $request->user()
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Resi {$shipment->tracking_number} ditugaskan ke pengemudi.");
    }
}
