<?php

declare(strict_types=1);

namespace Modules\Wms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Wms\Application\Actions\AllocateOutboundWavePickingAction;
use Modules\Wms\Application\Actions\ExecuteDirectedPutawayAction;
use Modules\Wms\Application\Actions\ProcessCrossDockingTransferAction;
use Modules\Wms\Application\Services\WmsOperationalAnalyticsService;
use Modules\Wms\Domain\Models\Bin;
use Modules\Wms\Domain\Models\DockAppointment;
use Modules\Wms\Domain\Models\Task;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\Models\Wave;
use Modules\Wms\Domain\Models\Zone;
use Modules\Wms\Domain\Services\DockSchedulingOptimizationService;
use Modules\Wms\Domain\Services\WavePickPathOptimizationService;

final class WmsWaveAndDockController extends Controller
{
    public function __construct(
        private readonly AllocateOutboundWavePickingAction $allocateWaveAction,
        private readonly ProcessCrossDockingTransferAction $crossDockAction,
        private readonly ExecuteDirectedPutawayAction $putawayAction,
        private readonly WavePickPathOptimizationService $pickPathOptimizer,
        private readonly DockSchedulingOptimizationService $dockOptimizer,
        private readonly WmsOperationalAnalyticsService $analyticsService,
    ) {}

    public function waveIndex(): View
    {
        $waves = Wave::query()->latest('id')->limit(20)->get();
        $tasks = Task::query()
            ->where('kind', 'pick')
            ->with(['bin.rack.zone'])
            ->latest('id')
            ->limit(50)
            ->get();

        $pickOptimization = $this->pickPathOptimizer->optimizeFromEloquentTasks($tasks);
        $analytics = $this->analyticsService->summarize();
        $waveRows = $this->analyticsService->pickingTableRows(20);

        return view('wms::wave_picking', compact(
            'waves',
            'tasks',
            'pickOptimization',
            'analytics',
            'waveRows'
        ));
    }

    public function dockIndex(): View
    {
        $docks = DockAppointment::query()
            ->with('warehouse')
            ->latest('id')
            ->limit(20)
            ->get();
        $warehouses = Warehouse::query()->where('is_active', true)->get();

        $dockEvaluation = $this->dockOptimizer->evaluateFromEloquentAppointments($docks);
        $analytics = $this->analyticsService->summarize();
        $dockRows = $this->analyticsService->dockTableRows(20);

        return view('wms::cross_dock', compact(
            'docks',
            'warehouses',
            'dockEvaluation',
            'analytics',
            'dockRows'
        ));
    }

    public function exportPickingCsv(): Response
    {
        $csv = $this->analyticsService->buildPickingCsv();

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="wms-picking.csv"',
        ]);
    }

    public function exportDocksCsv(): Response
    {
        $csv = $this->analyticsService->buildDocksCsv();

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="wms-docks.csv"',
        ]);
    }

    public function putawayIndex(): View
    {
        $bins = Bin::query()->where('is_active', true)->limit(30)->get();
        $zones = Zone::query()->where('is_active', true)->get();

        return view('wms::directed_putaway', compact('bins', 'zones'));
    }

    public function allocateWave(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'strategy' => 'required|string|in:fifo,fefo,zone,batch',
            'requirements' => 'required|array',
            'requirements.*.product_id' => 'required|integer',
            'requirements.*.qty' => 'required|integer|min:1',
        ]);

        $result = $this->allocateWaveAction->execute($data['strategy'], $data['requirements']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'wave_id' => $result['wave']->id,
                'task_count' => count($result['tasks']),
            ]);
        }

        return redirect()->back()->with('success', 'Wave picking berhasil dialokasikan.');
    }

    public function processCrossDock(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'dock_appointment_id' => 'required|integer|exists:wms_dock_appointments,id',
            'origin_warehouse_id' => 'required|string|exists:wms_warehouses,id',
            'destination_warehouse_id' => 'required|string|exists:wms_warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        $dock = DockAppointment::findOrFail($data['dock_appointment_id']);
        $origin = Warehouse::findOrFail($data['origin_warehouse_id']);
        $dest = Warehouse::findOrFail($data['destination_warehouse_id']);

        $transfer = $this->crossDockAction->execute($dock, $origin, $dest, $data['items']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'transfer_id' => $transfer->id,
                'status' => $transfer->status,
            ]);
        }

        return redirect()->back()->with('success', 'Cross docking berhasil diproses.');
    }

    public function executeDirectedPutaway(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer',
            'qty' => 'required|integer|min:1',
            'bin_id' => 'nullable|integer|exists:wms_bins,id',
            'zone_id' => 'nullable|integer|exists:wms_zones,id',
            'lot_number' => 'nullable|string|max:60',
            'status' => 'nullable|string|in:available,quarantine,blocked',
        ]);

        $bin = ! empty($data['bin_id']) ? Bin::find($data['bin_id']) : null;
        $zone = ! empty($data['zone_id']) ? Zone::find($data['zone_id']) : null;

        $result = $this->putawayAction->execute(
            productId: $data['product_id'],
            qty: $data['qty'],
            preferredBin: $bin,
            targetZone: $zone,
            lotNumber: $data['lot_number'] ?? null,
            status: $data['status'] ?? 'available'
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'bin_id' => $result['stock']->bin_id,
                'task_id' => $result['task']->id,
            ]);
        }

        return redirect()->back()->with('success', 'Directed putaway selesai.');
    }
}
