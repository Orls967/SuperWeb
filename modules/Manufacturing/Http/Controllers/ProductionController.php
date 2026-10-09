<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Manufacturing\Application\Services\CostingService;
use Modules\Manufacturing\Application\Services\ProductionService;
use Modules\Manufacturing\Domain\Models\CostVersion;
use Modules\Manufacturing\Domain\Models\DowntimeLog;
use Modules\Manufacturing\Domain\Models\MaterialBalance;
use Modules\Manufacturing\Domain\Models\MaterialLot;
use Modules\Manufacturing\Domain\Models\OrderCost;
use Modules\Manufacturing\Domain\Models\PlannedOrder;
use Modules\Manufacturing\Domain\Models\ProductionOrder;
use Modules\Manufacturing\Domain\Models\WorkCenter;

class ProductionController extends Controller
{
    public function __construct(
        private readonly ProductionService $service,
        private readonly CostingService $costing,
    ) {}

    public function index(): View
    {
        return view('manufacturing::production', [
            'orders' => ProductionOrder::with(['material', 'routing.operations.workCenter'])
                ->orderByDesc('created_at')->limit(100)->get(),
            'planned' => PlannedOrder::where('kind', 'production')->where('status', 'planned')->orderBy('due_date')->get(),
            'lots' => MaterialLot::with('material')->where('status', 'active')->orderBy('produced_at')->limit(50)->get(),
            'balances' => MaterialBalance::with('material')->orderBy('material_id')->get(),
            'downtimes' => DowntimeLog::with('workCenter')->orderByDesc('started_at')->limit(20)->get(),
            'workCenters' => WorkCenter::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    /** 38.7 Laporan: margin per produk + drill-down cost per order. */
    public function costing(): View
    {
        return view('manufacturing::costing', [
            'margins' => $this->costing->marginReport(),
            'costVersions' => CostVersion::withCount('standardCosts')->orderByDesc('created_at')->get(),
            'orderCosts' => OrderCost::with('order.material')->orderByDesc('computed_at')->limit(50)->get(),
        ]);
    }

    public function submitCostVersion(CostVersion $version, Request $request): RedirectResponse
    {
        $this->costing->submitCostVersion($version, $request->user());

        return back()->with('success', 'Versi biaya diajukan untuk approval.');
    }

    public function approveCostVersion(CostVersion $version, Request $request): RedirectResponse
    {
        $this->costing->approveCostVersion($version, $request->user());

        return back()->with('success', 'Versi biaya disetujui.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'material_id' => 'required|uuid|exists:mfg_materials,id',
            'plant_id' => 'nullable|uuid|exists:mfg_plants,id',
            'qty' => 'required|numeric|gt:0',
            'kind' => 'required|in:standard,rework,subcontract',
            'due_date' => 'nullable|date',
            'scrap_tolerance_percent' => 'nullable|numeric|between:0,100',
            'notes' => 'nullable|string|max:1000',
        ]);
        $this->service->createProductionOrder($data, $request->user());

        return back()->with('success', 'Order produksi dibuat.');
    }

    public function transition(ProductionOrder $order, Request $request): RedirectResponse
    {
        $data = $request->validate(['to' => 'required|in:released,in_progress,completed,closed,cancelled']);
        $this->service->transition($order, $data['to'], $request->user());

        return back()->with('success', "Order {$order->number} → {$data['to']}.");
    }

    public function issue(ProductionOrder $order, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'material_id' => 'required|uuid|exists:mfg_materials,id',
            'qty' => 'required|numeric|gt:0', 'method' => 'required|in:fifo,fefo,manual',
        ]);
        $issues = $this->service->issueMaterials($order, [$data], $request->user());
        $alert = $issues[0]->alert ?? null;

        return back()->with('success', $alert !== null
            ? 'Issue sebagian (alert: '.$alert.').'
            : 'Bahan dikeluarkan.');
    }

    public function startOperation(ProductionOrder $order, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sequence' => 'required|integer|min:1', 'work_center_id' => 'nullable|string|exists:mfg_work_centers,id',
        ]);
        $this->service->startOperation($order, (int) $data['sequence'], $data['work_center_id'] ?? null);

        return back()->with('success', 'Operasi dimulai.');
    }

    public function finishOperation(ProductionOrder $order, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sequence' => 'required|integer|min:1', 'qty_good' => 'required|numeric|min:0',
            'qty_scrap' => 'nullable|numeric|min:0', 'qty_rework' => 'nullable|numeric|min:0',
        ]);
        $report = $order->operationReports()
            ->where('sequence', $data['sequence'])
            ->whereNull('finished_at')
            ->orderByDesc('started_at')
            ->firstOrFail();

        $result = $this->service->finishOperation(
            $report,
            (float) $data['qty_good'],
            (float) ($data['qty_scrap'] ?? 0),
            (float) ($data['qty_rework'] ?? 0),
        );

        return back()->with('success', 'Operasi selesai; total baik = '.$result['order']->qty_completed.'.');
    }

    public function receiveFg(ProductionOrder $order, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'qty' => 'required|numeric|gt:0', 'lot_number' => 'nullable|string|max:60',
        ]);
        $this->service->receiveFg($order, (float) $data['qty'], $request->user(), $data['lot_number'] ?? null);

        return back()->with('success', 'Barang jadi diterima ke stok (lot dibuat).');
    }

    public function startDowntime(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_center_id' => 'required|string|exists:mfg_work_centers,id',
            'reason_code' => 'required|in:machine_down,material_wait,setup,break,other',
            'detail' => 'nullable|string|max:500',
        ]);
        $this->service->startDowntime($data['work_center_id'], $data['reason_code'], $request->user(), null, $data['detail'] ?? null);

        return back()->with('success', 'Downtime dicatat.');
    }

    public function endDowntime(int $downtime): RedirectResponse
    {
        $this->service->endDowntime(DowntimeLog::findOrFail($downtime));

        return back()->with('success', 'Downtime selesai.');
    }

    public function recordScrap(ProductionOrder $order, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => 'required|in:scrap,rework', 'qty' => 'required|numeric|gt:0',
            'reason' => 'required|string|max:300', 'cost_idr' => 'nullable|integer|min:0',
        ]);
        $result = $this->service->recordScrapRework(
            $order, $data['kind'], (float) $data['qty'], $data['reason'], $request->user(), (int) ($data['cost_idr'] ?? 0)
        );

        return back()->with(
            'success',
            $result['ncrRequired']
                ? sprintf('Scrap %.2f%% melebihi toleransi — NCR dibutuhkan (Fase 39).', $result['scrapPct'])
                : 'Scrap/rework dicatat.'
        );
    }

    public function invariants(ProductionOrder $order): RedirectResponse
    {
        $result = $this->service->checkInvariants($order);

        return back()->with(
            $result['ok'] ? 'success' : 'success',
            $result['ok'] ? 'Invarian terpenuhi.' : 'Invarian melanggar: '.implode('; ', $result['issues'])
        );
    }
}
