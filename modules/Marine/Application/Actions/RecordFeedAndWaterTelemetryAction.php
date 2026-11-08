<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Actions;

use Modules\Marine\Application\Services\FeedAndWaterQualityTelemetryService;
use Modules\Marine\Domain\Models\WaterQualityTelemetry;

class RecordFeedAndWaterTelemetryAction
{
    protected FeedAndWaterQualityTelemetryService $telemetryService;

    public function __construct(FeedAndWaterQualityTelemetryService $telemetryService)
    {
        $this->telemetryService = $telemetryService;
    }

    /**
     * @param  array<string, mixed>  $telemetryData
     */
    public function execute(int $batchId, array $telemetryData): WaterQualityTelemetry
    {
        return $this->telemetryService->recordWaterTelemetry($batchId, $telemetryData);
    }
}
