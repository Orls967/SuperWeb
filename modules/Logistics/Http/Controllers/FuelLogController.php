<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Logistics\Application\Actions\RecordFuelLogAction;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\FuelLog;
use Modules\Logistics\Domain\Models\Truck;
use RuntimeException;

class FuelLogController extends Controller
{
    protected function isStaff(Request $request): bool
    {
        $user = $request->user();

        return $user && ($user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher());
    }

    protected function authorizeAccess(Request $request): void
    {
        abort_unless($this->isStaff($request) || ($request->user() && $request->user()->isDriver()), 403, 'Akses ditolak.');
    }

    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        $driver = $request->user()->isDriver() ? Driver::where('user_id', $request->user()->id)->first() : null;

        $logs = FuelLog::with(['truck', 'driver.user'])
            ->when(! $this->isStaff($request), fn ($q) => $q->where('driver_id', $driver?->id ?? 0))
            ->when($request->boolean('anomaly'), fn ($q) => $q->where('is_anomaly', true))
            ->latest('filled_at')->limit(60)->get();

        return view('logistics::fuel.index', [
            'logs' => $logs,
            'trucks' => Truck::orderBy('plate_number')->get(['id', 'plate_number', 'odometer_m']),
            'isStaff' => $this->isStaff($request),
            'anomalyCount' => FuelLog::where('is_anomaly', true)->count(),
            'monthCost' => (int) FuelLog::where('filled_at', '>=', now()->startOfMonth())->sum('total_cost_idr'),
            'onlyAnomaly' => $request->boolean('anomaly'),
        ]);
    }

    public function store(Request $request, RecordFuelLogAction $action): RedirectResponse
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'truck_id' => 'required|integer|exists:lgx_trucks,id',
            'liters' => 'required|numeric|min:0.001|max:2000',
            'price_per_liter_idr' => 'required|integer|min:1',
            'odometer_km' => 'required|numeric|min:0',
        ]);

        $driver = $request->user()->isDriver() ? Driver::where('user_id', $request->user()->id)->first() : null;

        try {
            $log = $action->execute(
                $request->user(),
                Truck::findOrFail($data['truck_id']),
                (int) round($data['liters'] * 1000),
                (int) $data['price_per_liter_idr'],
                (int) round($data['odometer_km'] * 1000),
                null,
                $driver
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with($log->is_anomaly ? 'warning' : 'success', $log->is_anomaly ? "BBM dicatat, ANOMALI: {$log->anomaly_note}" : 'Pengisian BBM dicatat.');
    }
}
