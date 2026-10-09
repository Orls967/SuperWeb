<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Manufacturing\Application\Services\HseService;
use Modules\Manufacturing\Application\Services\MaintenanceService;
use Modules\Manufacturing\Domain\Models\EquipmentPart;
use Modules\Manufacturing\Domain\Models\HseIncident;
use Modules\Manufacturing\Domain\Models\MaintenanceOrder;
use Modules\Manufacturing\Domain\Models\OeeSummary;
use Modules\Manufacturing\Domain\Models\WorkCenter;
use Modules\Manufacturing\Domain\Models\WorkPermit;

class MaintenanceController extends Controller
{
    public function __construct(
        private readonly MaintenanceService $maintenance,
        private readonly HseService $hse,
    ) {}

    public function index(): View
    {
        $workCenters = WorkCenter::where('is_active', true)->orderBy('code')->get();

        return view('manufacturing::maintenance', [
            'workCenters' => $workCenters,
            'orders' => MaintenanceOrder::with('workCenter')->orderByDesc('created_at')->limit(50)->get(),
            'oees' => OeeSummary::with('workCenter')->orderByDesc('period_date')->limit(30)->get(),
            'pareto' => $this->maintenance->downtimePareto(now()->subDays(7)->toDateString()),
            'backlog' => $this->maintenance->maintenanceBacklog(),
            'lowStock' => $this->maintenance->lowStockParts(),
            'incidents' => HseIncident::with('workCenter')->orderByDesc('occurred_at')->limit(30)->get(),
            'permits' => WorkPermit::orderByDesc('valid_until')->limit(30)->get(),
            'parts' => EquipmentPart::with('workCenter')->orderBy('part_code')->get(),
        ]);
    }

    public function storeOrder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_center_id' => 'required|string|exists:mfg_work_centers,id',
            'description' => 'required|string|max:500',
            'kind' => 'required|in:corrective,preventive,predictive',
            'priority' => 'required|in:low,normal,high,critical',
            'due_date' => 'nullable|date', 'labor_cost_idr' => 'nullable|integer|min:0',
            'parts_cost_idr' => 'nullable|integer|min:0',
        ]);
        $workCenter = WorkCenter::findOrFail($data['work_center_id']);
        unset($data['work_center_id']);
        $this->maintenance->createMaintenanceOrder($workCenter, $data['description'], $request->user(), $data);

        return back()->with('success', 'Work order pemeliharaan dibuat.');
    }

    public function transitionOrder(MaintenanceOrder $order, Request $request): RedirectResponse
    {
        $data = $request->validate(['to' => 'required|in:in_progress,completed,cancelled']);
        $this->maintenance->transitionMaintenance($order, $data['to']);

        return back()->with('success', "WO {$order->number} → {$data['to']}.");
    }

    public function storeSensor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_center_id' => 'required|string|exists:mfg_work_centers,id',
            'sensor_code' => 'required|string|max:40',
            'metric' => 'required|in:temperature,vibration,current',
            'value' => 'required|numeric',
            'threshold_high' => 'nullable|numeric', 'threshold_low' => 'nullable|numeric',
        ]);
        $workCenter = WorkCenter::findOrFail($data['work_center_id']);
        $result = $this->maintenance->recordSensorReading(
            $workCenter, $data['sensor_code'], $data['metric'], (float) $data['value'], $request->user(),
            isset($data['threshold_high']) ? (float) $data['threshold_high'] : null,
            isset($data['threshold_low']) ? (float) $data['threshold_low'] : null,
        );

        return back()->with('success', $result['alarm']
            ? 'Alarm! WO korektif dibuat otomatis: '.$result['maintenance']->number
            : 'Bacaan sensor tercatat (dalam ambang).');
    }

    public function computeOee(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_center_id' => 'required|string|exists:mfg_work_centers,id',
            'period_date' => 'required|date', 'planned_minutes' => 'required|integer|min:1|max:1440',
        ]);
        $result = $this->maintenance->computeOee(
            WorkCenter::findOrFail($data['work_center_id']),
            $data['period_date'],
            (int) $data['planned_minutes'],
        );

        return back()->with('success', sprintf(
            'OEE %s: A %.1f%% × P %.1f%% × Q %.1f%% = %.1f%%',
            $data['period_date'], $result['availability'], $result['performance'], $result['quality'], $result['oee']
        ));
    }

    public function storePart(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_center_id' => 'required|string|exists:mfg_work_centers,id',
            'part_code' => 'required|string|max:60', 'name' => 'required|string|max:160',
            'qty_per_equipment' => 'nullable|integer|min:1', 'min_stock' => 'nullable|numeric|min:0',
            'unit_cost_idr' => 'nullable|integer|min:0',
        ]);
        $workCenter = WorkCenter::findOrFail($data['work_center_id']);
        unset($data['work_center_id']);
        $this->maintenance->addEquipmentPart($workCenter, $data);

        return back()->with('success', 'Suku cadang peralatan disimpan.');
    }

    // ── 40.6 K3 ──────────────────────────────────────────────────────────

    public function storeIncident(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'work_center_id' => 'nullable|string|exists:mfg_work_centers,id',
            'kind' => 'required|in:incident,near_miss,injury,environmental',
            'severity' => 'required|in:low,moderate,high,critical',
            'description' => 'nullable|string', 'due_date' => 'nullable|date',
        ]);
        $this->hse->report($data, $request->user());

        return back()->with('success', 'Insiden K3 tercatat.');
    }

    public function closeIncident(HseIncident $incident): RedirectResponse
    {
        try {
            $this->hse->close($incident);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['incident' => $e->getMessage()]);
        }

        return back()->with('success', "Insiden {$incident->number} ditutup.");
    }

    public function storePermit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_center_id' => 'required|string|exists:mfg_work_centers,id',
            'permit_type' => 'required|in:hot_work,confined_space',
            'hazards' => 'nullable|string', 'controls' => 'nullable|string',
            'valid_from' => 'nullable|date', 'valid_until' => 'required|date',
        ]);
        $workCenterId = $data['work_center_id'];
        unset($data['work_center_id']);
        $this->hse->requestPermit($workCenterId, $data, $request->user());

        return back()->with('success', 'Izin kerja berisiko diajukan (menunggu approval four-eyes).');
    }

    public function approvePermit(WorkPermit $permit, Request $request): RedirectResponse
    {
        try {
            $this->hse->approvePermit($permit, $request->user());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withErrors(['permit' => $e->getMessage()]);
        }

        return back()->with('success', "Izin {$permit->number} aktif.");
    }
}
