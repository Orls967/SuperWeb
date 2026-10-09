<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Manufacturing\Application\Services\PlanningService;
use Modules\Manufacturing\Domain\Models\ForecastScenario;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\MaterialBalance;
use Modules\Manufacturing\Domain\Models\MpsHeader;
use Modules\Manufacturing\Domain\Models\MrpRun;
use Modules\Manufacturing\Domain\Models\PlannedOrder;
use Modules\Manufacturing\Domain\Models\PlanningParam;
use Modules\Manufacturing\Domain\Models\ScheduledReceipt;

class PlanningController extends Controller
{
    public function __construct(private readonly PlanningService $planning) {}

    public function index(): View
    {
        return view('manufacturing::planning', [
            'materials' => Material::orderBy('code')->get(),
            'params' => PlanningParam::with('material')->orderBy('material_id')->get(),
            'balances' => MaterialBalance::with('material')->orderBy('material_id')->get(),
            'receipts' => ScheduledReceipt::with('material')->where('status', 'open')->orderBy('due_date')->get(),
            'scenarios' => ForecastScenario::with('lines.material')->orderByDesc('created_at')->get(),
            'mpsHeaders' => MpsHeader::with('lines.material')->orderByDesc('created_at')->get(),
            'runs' => MrpRun::withCount(['requirements', 'plannedOrders', 'capacityLoads'])->orderByDesc('created_at')->limit(20)->get(),
            'plannedOrders' => PlannedOrder::with('material')->whereIn('status', ['planned', 'firm'])->orderBy('due_date')->limit(100)->get(),
        ]);
    }

    public function storeParams(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'material_id' => 'required|uuid|exists:mfg_materials,id',
            'safety_stock' => 'required|numeric|min:0', 'reorder_point' => 'required|numeric|min:0',
            'lead_time_days' => 'required|integer|min:0|max:3650', 'moq' => 'required|numeric|gt:0',
            'lot_sizing' => 'required|in:l4l,fixed,periodic,eoq',
            'fixed_order_qty' => 'nullable|numeric|gt:0', 'period_weeks' => 'nullable|integer|min:1|max:52',
            'ordering_cost_idr' => 'nullable|integer|min:0', 'holding_cost_per_unit_year_idr' => 'nullable|integer|min:0',
        ]);
        $materialId = $data['material_id'];
        unset($data['material_id']);
        $this->planning->savePlanningParams($materialId, $data);

        return back()->with('success', 'Parameter perencanaan disimpan.');
    }

    public function storeBalance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'material_id' => 'required|uuid|exists:mfg_materials,id',
            'qty_on_hand' => 'required|numeric|min:0', 'qty_reserved' => 'required|numeric|min:0',
        ]);
        if ((float) $data['qty_reserved'] > (float) $data['qty_on_hand']) {
            return back()->withErrors(['qty_reserved' => 'Qty reserved tidak boleh melebihi on-hand.']);
        }
        MaterialBalance::updateOrCreate(['material_id' => $data['material_id']], $data);

        return back()->with('success', 'Posisi stok material disimpan.');
    }

    public function storeReceipt(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'material_id' => 'required|uuid|exists:mfg_materials,id', 'due_date' => 'required|date',
            'qty' => 'required|numeric|gt:0', 'source_type' => 'required|in:po,manual,production',
            'source_ref' => 'nullable|string|max:80',
        ]);
        ScheduledReceipt::create($data);

        return back()->with('success', 'Penerimaan terjadwal disimpan.');
    }

    public function storeForecast(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160', 'material_id' => 'required|uuid|exists:mfg_materials,id',
            'period_start' => 'required|date', 'qty' => 'required|numeric|gt:0',
            'kind' => 'required|in:order,forecast', 'notes' => 'nullable|string|max:1000',
        ]);
        $this->planning->createForecastScenario(
            $data['name'],
            [['material_id' => $data['material_id'], 'period_start' => $data['period_start'], 'qty' => $data['qty'], 'kind' => $data['kind']]],
            $request->user(),
            $data['notes'] ?? ''
        );

        return back()->with('success', 'Skenario forecast baru dibuat (versioned).');
    }

    public function activateForecast(ForecastScenario $scenario): RedirectResponse
    {
        $this->planning->activateForecastScenario($scenario);

        return back()->with('success', 'Skenario forecast diaktifkan.');
    }

    public function storeMps(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160', 'material_id' => 'required|uuid|exists:mfg_materials,id',
            'period_start' => 'required|date', 'qty' => 'required|numeric|gt:0',
            'freeze_days' => 'required|integer|min:0|max:365', 'horizon_end' => 'nullable|date|after_or_equal:period_start',
        ]);
        $this->planning->createMps(
            $data['name'],
            [['material_id' => $data['material_id'], 'period_start' => $data['period_start'], 'qty' => $data['qty']]],
            $request->user(),
            $data['freeze_days'],
            $data['horizon_end'] ?? null
        );

        return back()->with('success', 'MPS aktif disimpan. Baris time fence ditandai frozen.');
    }

    public function runMrp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'horizon_days' => 'required|integer|min:7|max:365',
            'bucket_days' => 'required|integer|min:1|max:31', 'scenario' => 'sometimes|boolean',
        ]);
        $run = $this->planning->runMrp($data['horizon_days'], $data['bucket_days'], (bool) ($data['scenario'] ?? false));

        return back()->with('success', 'MRP run '.$run->id.' selesai.');
    }

    public function firmOrder(PlannedOrder $order, Request $request): RedirectResponse
    {
        $data = $request->validate(['reservation_kind' => 'required|in:hard,soft']);
        $this->planning->firmPlannedOrder($order, $data['reservation_kind']);

        return back()->with('success', 'Order dijadikan firm; reservasi bahan dibuat.');
    }

    public function proposePurchases(MrpRun $run, Request $request): RedirectResponse
    {
        $numbers = $this->planning->proposePurchases($run, $request->user());

        return back()->with('success', $numbers === [] ? 'Tidak ada planned purchase baru.' : 'PR dibuat: '.implode(', ', $numbers));
    }
}
