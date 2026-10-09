<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Manufacturing\Application\Services\ManufacturingService;
use Modules\Manufacturing\Domain\Models\Bom;
use Modules\Manufacturing\Domain\Models\Formula;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\Plant;
use Modules\Manufacturing\Domain\Models\Routing;
use Modules\Manufacturing\Domain\Models\Worker;

class ManufacturingController extends Controller
{
    public function __construct(private readonly ManufacturingService $service) {}

    public function index(): View
    {
        return view('manufacturing::index', [
            'plants' => Plant::withCount(['areas', 'workCenters'])->orderBy('code')->get(),
            'materials' => Material::orderBy('code')->get(),
            'boms' => Bom::with(['outputMaterial', 'lines.inputMaterial'])->orderByDesc('created_at')->limit(50)->get(),
            'routings' => Routing::with(['outputMaterial', 'operations.workCenter'])->orderByDesc('created_at')->limit(50)->get(),
            'formulas' => Formula::with('outputMaterial')->orderByDesc('created_at')->limit(50)->get(),
            'workers' => Worker::orderBy('employee_code')->get(),
        ]);
    }

    public function storePlant(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:40', 'name' => 'required|string|max:180',
            'type' => 'required|in:factory,central_kitchen,workshop',
            'timezone' => 'required|string|max:64',
            'nominal_capacity_per_day' => 'required|integer|min:0',
            'capacity_uom' => 'required|string|max:32',
        ]);
        $this->service->createPlant($data);

        return back()->with('success', 'Plant berhasil dibuat.');
    }

    public function storeMaterial(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:60', 'name' => 'required|string|max:200',
            'kind' => 'required|in:raw,wip,finished,packaging,by_product,co_product',
            'base_uom' => 'required|string|max:20',
            'lot_tracked' => 'sometimes|boolean', 'expiry_tracked' => 'sometimes|boolean',
            'serial_tracked' => 'sometimes|boolean', 'description' => 'nullable|string|max:1000',
        ]);
        $this->service->createMaterial($data);

        return back()->with('success', 'Material berhasil dibuat.');
    }

    public function storeBom(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'output_material_id' => 'required|uuid|exists:mfg_materials,id',
            'name' => 'required|string|max:180', 'output_qty' => 'required|numeric|gt:0',
            'output_uom' => 'required|string|max:20', 'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'change_reason' => 'nullable|string|max:255', 'lines' => 'required|array|min:1',
            'lines.*.input_material_id' => 'required|uuid|exists:mfg_materials,id',
            'lines.*.qty' => 'required|numeric|gt:0', 'lines.*.uom' => 'required|string|max:20',
            'lines.*.scrap_percent' => 'nullable|numeric|between:0,100',
            'lines.*.is_alternative' => 'sometimes|boolean',
            'lines.*.substitution_group' => 'nullable|string|max:40',
            'lines.*.is_by_product' => 'sometimes|boolean', 'lines.*.is_co_product' => 'sometimes|boolean',
            'lines.*.allocation_percent' => 'nullable|numeric|between:0,100',
        ]);
        $this->service->createBom($data, $request->user());

        return back()->with('success', 'BOM berhasil dibuat.');
    }

    public function storeRouting(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'output_material_id' => 'required|uuid|exists:mfg_materials,id',
            'name' => 'required|string|max:180', 'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'change_reason' => 'nullable|string|max:255', 'operations' => 'required|array|min:1',
            'operations.*.name' => 'required|string|max:160',
            'operations.*.work_center_id' => 'nullable|uuid|exists:mfg_work_centers,id',
            'operations.*.setup_minutes' => 'nullable|integer|min:0',
            'operations.*.run_minutes_per_unit' => 'nullable|integer|min:0',
            'operations.*.work_instructions' => 'nullable|string',
            'operations.*.inspection_point' => 'sometimes|boolean',
        ]);
        $this->service->createRouting($data);

        return back()->with('success', 'Routing berhasil dibuat.');
    }

    public function storeFormula(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'output_material_id' => 'required|uuid|exists:mfg_materials,id',
            'name' => 'required|string|max:180',
            'standard_yield_percent' => 'required|numeric|gt:0|lte:1000',
            'yield_tolerance_percent' => 'required|numeric|between:0,100',
            'active_ingredients' => 'nullable|array', 'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'change_reason' => 'nullable|string|max:255',
        ]);
        $this->service->createFormula($data, $request->user());

        return back()->with('success', 'Formula draft berhasil dibuat.');
    }

    public function submitFormula(Formula $formula, Request $request): RedirectResponse
    {
        $this->service->submitFormula($formula, $request->user());

        return back()->with('success', 'Formula diajukan untuk approval.');
    }

    public function approveFormula(Formula $formula, Request $request): RedirectResponse
    {
        $this->service->approveFormula($formula, $request->user());

        return back()->with('success', 'Formula disetujui.');
    }

    public function storeWorker(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_code' => 'required|string|max:40', 'name' => 'required|string|max:160',
            'user_id' => 'nullable|integer|exists:users,id',
            'skills' => 'nullable|array', 'certifications' => 'nullable|array',
        ]);
        $this->service->createWorker($data);

        return back()->with('success', 'Pekerja berhasil dibuat.');
    }
}
