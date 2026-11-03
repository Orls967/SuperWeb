<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Manufacturing\Application\Services\AgriProcessing\AgriProcessingService;
use Modules\Manufacturing\Domain\Models\AgriProcessing\MfgAgriBatch;

class AgriProcessingController extends Controller
{
    public function __construct(
        private readonly AgriProcessingService $service
    ) {}

    public function index(): View
    {
        $batches = MfgAgriBatch::with('inputs')->orderByDesc('id')->limit(50)->get();

        return view('manufacturing::agri_processing', [
            'batches' => $batches,
        ]);
    }

    public function optimize(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'target_weight_kg' => 'required|integer|min:1',
            'min_protein_bps' => 'required|integer|min:0',
            'max_moisture_bps' => 'required|integer|min:0',
            'lots' => 'required|array|min:1',
            'lots.*.id' => 'required|string',
            'lots.*.cost_per_kg' => 'required|integer|min:1',
            'lots.*.protein_bps' => 'required|integer|min:0',
            'lots.*.moisture_bps' => 'required|integer|min:0',
            'lots.*.available_kg' => 'required|integer|min:1',
        ]);

        $result = $this->service->optimizeBlending(
            lots: $data['lots'],
            target: [
                'target_weight_kg' => (int) $data['target_weight_kg'],
                'min_protein_bps' => (int) $data['min_protein_bps'],
                'max_moisture_bps' => (int) $data['max_moisture_bps'],
            ]
        );

        return back()->with('blending_result', $result);
    }
}
