<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Manufacturing\Application\Services\QualityService;
use Modules\Manufacturing\Domain\Models\Capa;
use Modules\Manufacturing\Domain\Models\Gauge;
use Modules\Manufacturing\Domain\Models\Inspection;
use Modules\Manufacturing\Domain\Models\InspectionPlan;
use Modules\Manufacturing\Domain\Models\MaterialLot;
use Modules\Manufacturing\Domain\Models\Ncr;
use Modules\Manufacturing\Domain\Models\Recall;

class QualityController extends Controller
{
    public function __construct(private readonly QualityService $service) {}

    public function index(): View
    {
        return view('manufacturing::quality', [
            'plans' => InspectionPlan::orderBy('stage')->get(),
            'inspections' => Inspection::orderByDesc('created_at')->limit(30)->get(),
            'ncrs' => Ncr::orderByDesc('created_at')->limit(30)->get(),
            'capas' => Capa::orderBy('due_date')->limit(30)->get(),
            'gauges' => Gauge::orderBy('calibration_due')->get(),
            'recalls' => Recall::with('lot')->orderByDesc('created_at')->limit(20)->get(),
            'lots' => MaterialLot::where('status', 'active')->orderByDesc('produced_at')->limit(30)->get(),
        ]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160', 'stage' => 'required|in:receiving,in_process,final',
            'spec_min' => 'nullable|numeric', 'spec_max' => 'nullable|numeric',
            'aql_percent' => 'required|numeric|between:0,100', 'sample_size' => 'required|integer|min:1|max:200',
            'frequency' => 'required|in:per_batch,per_shift,per_order',
            'characteristic_names' => 'required|string|max:300',
        ]);

        $characteristics = array_map(
            static fn (string $n) => ['name' => trim($n), 'type' => 'variable'],
            explode(',', $data['characteristic_names'])
        );

        $this->service->createInspectionPlan($data, $characteristics);

        return back()->with('success', 'Rencana inspeksi dibuat.');
    }

    public function inspect(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'stage' => 'required|in:receiving,in_process,final',
            'subject_type' => 'required|in:grn,production_order,lot',
            'subject_id' => 'required|string|max:36', 'lot_id' => 'nullable|uuid|exists:mfg_material_lots,id',
            'plan_id' => 'nullable|uuid|exists:mfg_inspection_plans,id',
            'gauge_id' => 'nullable|integer|exists:mfg_gauges,id',
            'readings' => 'required|string|max:1000',
        ]);

        $readings = array_values(array_map('floatval', preg_split('/[\s,]+/', trim($data['readings']), -1, PREG_SPLIT_NO_EMPTY)));

        try {
            $result = $this->service->inspect(
                $data['stage'], $data['subject_type'], $data['subject_id'], $readings,
                $request->user(), $data['plan_id'] ?? null, $data['gauge_id'] ?? null, $data['lot_id'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['readings' => $e->getMessage()]);
        }

        return back()->with('success', 'Inspeksi tercatat: '.$result['result'].' ('.$result['out_of_spec'].' di luar spesifikasi).');
    }

    /** Ajukan dispensasi (empat mata: menyetujui harus pengguna lain). */
    public function submitWaiver(Inspection $inspection, Request $request): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:300']);
        $this->service->submitWaiver($inspection, $request->user(), $data['reason']);

        return back()->with('success', 'Dispensasi diajukan — menunggu persetujuan admin lain.');
    }

    public function approveWaiver(Inspection $inspection, Request $request): RedirectResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:300']);
        try {
            $this->service->approveWaiver($inspection, $request->user(), $data['note'] ?? '');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withErrors(['inspection' => $e->getMessage()]);
        }

        return back()->with('success', 'Dispensasi inspeksi disetujui.');
    }

    public function storeNcr(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:300', 'description' => 'nullable|string',
            'severity' => 'required|in:minor,major,critical', 'source' => 'required|in:inspection,scrap,supplier',
            'supplier_id' => 'nullable|string|max:36', 'lot_id' => 'nullable|uuid|exists:mfg_material_lots,id',
            'due_date' => 'nullable|date',
        ]);
        $this->service->openNcr($data, $request->user());

        return back()->with('success', 'NCR dibuka.');
    }

    public function storeCapa(Ncr $ncr, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => 'required|in:corrective,preventive',
            'action' => 'required|string|max:1000', 'due_date' => 'required|date',
        ]);
        $this->service->addCapa($ncr, $data['kind'], $data['action'], $data['due_date']);

        return back()->with('success', 'CAPA ditambahkan.');
    }

    public function completeCapa(Capa $capa, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'effectiveness' => 'required|in:effective,ineffective',
            'note' => 'required|string|max:500',
        ]);
        $this->service->completeCapa($capa, $data['effectiveness'], $data['note']);

        return back()->with('success', 'CAPA diverifikasi.');
    }

    public function addCertificate(MaterialLot $lot, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => 'required|in:coa,coc,sni,halal,bpom,gmp',
            'number' => 'required|string|max:80', 'expires_at' => 'nullable|date',
        ]);
        $this->service->addCertificate($lot, $data['type'], $data['number'], $data['expires_at'] ?? null, $request->user());

        return back()->with('success', 'Sertifikat tersimpan.');
    }

    public function releaseLot(MaterialLot $lot, Request $request): RedirectResponse
    {
        try {
            $this->service->releaseLot($lot, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['lot' => $e->getMessage()]);
        }

        return back()->with('success', 'Lot dilepas.');
    }

    public function trace(MaterialLot $lot): View
    {
        return view('manufacturing::trace', [
            'lot' => $lot,
            'backward' => $this->service->traceBackward($lot->id),
            'forward' => $this->service->traceForward($lot->id),
        ]);
    }

    public function storeRecall(MaterialLot $lot, Request $request): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:300']);
        $this->service->planRecall($lot, $data['reason'], $request->user());

        return back()->with('success', 'Recall direncanakan; lot dikarantina.');
    }

    public function notifyRecall(Recall $recall): RedirectResponse
    {
        $count = $this->service->notifyRecall($recall);

        return back()->with('success', "{$count} penerima dinotifikasi.");
    }

    public function completeRecall(Recall $recall, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cost_idr' => 'required|integer|min:0', 'destruction_note' => 'required|string|max:500',
        ]);
        $this->service->completeRecall($recall, $data['cost_idr'], $data['destruction_note']);

        return back()->with('success', 'Recall selesai; biaya dicatat.');
    }
}
