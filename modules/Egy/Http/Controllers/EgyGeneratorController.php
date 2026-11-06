<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Egy\Application\Services\MicrogridAndResilienceService;
use Modules\Egy\Domain\Models\GeneratorLog;

final class EgyGeneratorController extends Controller
{
    public function __construct(
        private readonly MicrogridAndResilienceService $service,
    ) {}

    public function index(): View
    {
        $logs = GeneratorLog::orderBy('id', 'desc')->paginate(20);

        return view('egy::generator.index', compact('logs'));
    }

    public function recordTest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'generator_asset_code' => ['required', 'string', 'max:50'],
            'site_name' => ['required', 'string', 'max:120'],
            'run_type' => ['required', 'string', 'in:weekly_no_load_test,weekly_load_test,grid_outage_support,black_start'],
            'start_time' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'fuel_consumed_liters' => ['required', 'integer', 'min:0'],
            'load_percentage_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $log = $this->service->recordGeneratorTest(
            generatorAssetCode: $validated['generator_asset_code'],
            siteName: $validated['site_name'],
            runType: $validated['run_type'],
            startTime: Carbon::parse($validated['start_time']),
            durationMinutes: (int) $validated['duration_minutes'],
            fuelConsumedLiters: (int) $validated['fuel_consumed_liters'],
            loadPercentageBps: (int) $validated['load_percentage_bps'],
            notes: $validated['notes'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Log pengujian genset berhasil disimpan dan emisi Scope 1 terhitung.',
            'data' => $log,
        ]);
    }
}
