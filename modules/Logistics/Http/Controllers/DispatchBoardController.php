<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
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
use Modules\Logistics\Domain\Models\Location;
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

        $schedules = Schedule::leftJoin('lgx_locations as o', 'o.id', '=', 'lgx_schedules.origin_location_id')
            ->leftJoin('lgx_locations as d', 'd.id', '=', 'lgx_schedules.destination_location_id')
            ->leftJoin('lgx_trucks as t', 't.id', '=', 'lgx_schedules.asset_id')
            ->leftJoin('lgx_drivers as drv', 'drv.id', '=', 'lgx_schedules.driver_id')
            ->leftJoin('users as u', 'u.id', '=', 'drv.user_id')
            ->select([
                'lgx_schedules.*',
                'o.name as _origin_name',
                'd.name as _dest_name',
                't.plate_number as _asset_plate',
                'u.name as _driver_name',
            ])
            ->where('lgx_schedules.mode', TransportMode::ROAD->value)
            ->whereIn('lgx_schedules.status', [ScheduleStatus::Scheduled->value, ScheduleStatus::Loading->value])
            ->orderBy('lgx_schedules.etd')
            ->limit(50)
            ->get();

        foreach ($schedules as $sched) {
            $sched->setRelation('origin', new Location(['name' => $sched->_origin_name]));
            $sched->setRelation('destination', new Location(['name' => $sched->_dest_name]));
            if ($sched->_asset_plate) {
                $sched->setRelation('asset', new Truck(['plate_number' => $sched->_asset_plate]));
            }
            if ($sched->_driver_name) {
                $driver = new Driver;
                $driver->setRelation('user', new User(['name' => $sched->_driver_name]));
                $sched->setRelation('driver', $driver);
            }
        }

        $trucks = Truck::whereIn('status', [FleetStatus::AVAILABLE->value, FleetStatus::ASSIGNED->value])
            ->orderBy('plate_number')
            ->get();

        $drivers = Driver::with('user')
            ->where('status', '!=', 'suspended')
            ->orderBy('driver_number')
            ->get();

        $assignableStatuses = array_map(fn ($s) => $s->value, AssignShipmentToDriverAction::ASSIGNABLE_STATUSES);
        $pendingShipments = Shipment::leftJoin('lgx_locations as o', 'o.id', '=', 'lgx_shipments.origin_location_id')
            ->leftJoin('lgx_locations as d', 'd.id', '=', 'lgx_shipments.destination_location_id')
            ->leftJoin('lgx_drivers as drv', 'drv.id', '=', 'lgx_shipments.driver_id')
            ->leftJoin('users as u', 'u.id', '=', 'drv.user_id')
            ->select([
                'lgx_shipments.*',
                'o.city as _origin_city',
                'd.city as _dest_city',
                'u.name as _driver_name',
            ])
            ->whereIn('lgx_shipments.status', $assignableStatuses)
            ->orderBy('lgx_shipments.booked_at')
            ->limit(30)
            ->get();

        foreach ($pendingShipments as $ps) {
            $ps->setRelation('origin', new Location(['city' => $ps->_origin_city]));
            $ps->setRelation('destination', new Location(['city' => $ps->_dest_city]));
            if ($ps->_driver_name) {
                $driver = new Driver;
                $driver->setRelation('user', new User(['name' => $ps->_driver_name]));
                $ps->setRelation('driver', $driver);
            }
        }

        $recentAssignments = DispatchAssignment::leftJoin('lgx_schedules as s', 's.id', '=', 'lgx_dispatch_assignments.schedule_id')
            ->leftJoin('lgx_trucks as t', 't.id', '=', 'lgx_dispatch_assignments.truck_id')
            ->leftJoin('lgx_drivers as d', 'd.id', '=', 'lgx_dispatch_assignments.driver_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->leftJoin('users as a', 'a.id', '=', 'lgx_dispatch_assignments.assigned_by')
            ->select([
                'lgx_dispatch_assignments.*',
                's.schedule_number as _schedule_number',
                't.plate_number as _plate_number',
                'u.name as _driver_user_name',
                'a.name as _assigner_name',
            ])
            ->orderByDesc('lgx_dispatch_assignments.id')
            ->limit(10)
            ->get();

        foreach ($recentAssignments as $ra) {
            if ($ra->_schedule_number) {
                $ra->setRelation('schedule', new Schedule(['schedule_number' => $ra->_schedule_number]));
            }
            if ($ra->_plate_number) {
                $ra->setRelation('truck', new Truck(['plate_number' => $ra->_plate_number]));
            }
            if ($ra->_driver_user_name) {
                $driver = new Driver;
                $driver->setRelation('user', new User(['name' => $ra->_driver_user_name]));
                $ra->setRelation('driver', $driver);
            }
            if ($ra->_assigner_name) {
                $ra->setRelation('assigner', new User(['name' => $ra->_assigner_name]));
            }
        }

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
