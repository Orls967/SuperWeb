<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Platform\Domain\Models\PhaseMetricSnapshot;
use App\Quality\Reporting\JsonMetricSnapshotExporter;

class QualityMetricsApiController extends Controller
{
    public function show(string $phaseId, JsonMetricSnapshotExporter $exporter): JsonResponse
    {
        $snapshot = PhaseMetricSnapshot::where('phase_id', $phaseId)->firstOrFail();

        return response()->json(
            json_decode($exporter->exportToJson($snapshot), true)
        );
    }
}

