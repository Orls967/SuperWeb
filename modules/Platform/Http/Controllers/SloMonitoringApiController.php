<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Modules\Platform\Domain\Models\ServiceSlo;
use App\Quality\Reporting\PrometheusMetricExporter;
use Modules\Platform\Domain\Models\PhaseMetricSnapshot;

class SloMonitoringApiController extends Controller
{
    public function index(): JsonResponse
    {
        $slos = ServiceSlo::all();

        return response()->json([
            'status' => 'success',
            'data' => $slos,
        ]);
    }

    public function metrics(PrometheusMetricExporter $exporter): Response
    {
        $snapshots = PhaseMetricSnapshot::all();
        $slos = ServiceSlo::all();

        $text = $exporter->exportMetrics($snapshots, $slos);

        return response($text, 200, [
            'Content-Type' => 'text/plain; version=0.0.4',
        ]);
    }
}

