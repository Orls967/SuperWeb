<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Quality\Observability\PrometheusMetricsCollector;
use Illuminate\Http\Response;

/**
 * Serves real-time Prometheus Exposition format metrics for SuperWeb 46 modules platform.
 */
final class OpsMetricsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(PrometheusMetricsCollector $collector): Response
    {
        return response($collector->render()."\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
