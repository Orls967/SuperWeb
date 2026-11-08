<?php

declare(strict_types=1);

namespace Modules\Marine\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marine\Application\Actions\RecordFeedAndWaterTelemetryAction;
use Modules\Marine\Application\Actions\StockPondBatchAction;
use Modules\Marine\Application\Services\AquacultureBatchLifecycleService;
use Modules\Marine\Application\Services\FeedAndWaterQualityTelemetryService;

class AquacultureBatchStockingController extends Controller
{
    protected StockPondBatchAction $stockAction;

    protected RecordFeedAndWaterTelemetryAction $telemetryAction;

    protected AquacultureBatchLifecycleService $lifecycleService;

    protected FeedAndWaterQualityTelemetryService $fcrService;

    public function __construct(
        StockPondBatchAction $stockAction,
        RecordFeedAndWaterTelemetryAction $telemetryAction,
        AquacultureBatchLifecycleService $lifecycleService,
        FeedAndWaterQualityTelemetryService $fcrService
    ) {
        $this->stockAction = $stockAction;
        $this->telemetryAction = $telemetryAction;
        $this->lifecycleService = $lifecycleService;
        $this->fcrService = $fcrService;
    }

    /**
     * Daftarkan siklus tebar benur baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hatchery_id' => 'required|integer',
            'pond_id' => 'required|integer',
            'species_scientific_name' => 'nullable|string',
            'commercial_name' => 'nullable|string',
            'stocking_count' => 'required|integer|min:100',
            'pond_area_hectares' => 'nullable|numeric|min:0.01',
            'initial_biomass_kg' => 'nullable|numeric',
            'target_harvest_biomass_kg' => 'nullable|numeric',
            'stocking_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $batch = $this->stockAction->execute((int) $validated['hatchery_id'], $validated);

        return response()->json([
            'success' => true,
            'message' => 'Batch budidaya tambak berhasil didaftarkan.',
            'batch' => $batch,
        ], 201);
    }

    /**
     * Catat sampling biomassa berkala petak tambak.
     */
    public function recordSampling(Request $request, int $batchId): JsonResponse
    {
        $validated = $request->validate([
            'mean_body_weight_grams' => 'required|numeric|min:0.1',
            'survival_rate_percentage' => 'required|numeric|min:1.0|max:100.0',
        ]);

        $batch = $this->lifecycleService->recordSampling(
            $batchId,
            (float) $validated['mean_body_weight_grams'],
            (float) $validated['survival_rate_percentage']
        );

        return response()->json([
            'success' => true,
            'message' => 'Sampling bobot dan populasi tambak diperbarui.',
            'batch' => $batch,
        ]);
    }

    /**
     * Catat telemetri sensor air IoT.
     */
    public function recordTelemetry(Request $request, int $batchId): JsonResponse
    {
        $validated = $request->validate([
            'dissolved_oxygen_mg_l' => 'required|numeric',
            'water_temperature_celsius' => 'required|numeric',
            'ph_level' => 'required|numeric',
            'salinity_ppt' => 'nullable|numeric',
            'sensor_device_id' => 'nullable|string',
        ]);

        $telemetry = $this->telemetryAction->execute($batchId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Telemetri kualitas air berhasil dicatat.',
            'telemetry' => $telemetry,
        ], 201);
    }

    /**
     * Evaluasi efisiensi pakan kumulatif tambak.
     */
    public function evaluateFcr(int $batchId): JsonResponse
    {
        $evaluation = $this->fcrService->evaluateBatchFcr($batchId);

        return response()->json([
            'success' => true,
            'evaluation' => $evaluation,
        ]);
    }
}
