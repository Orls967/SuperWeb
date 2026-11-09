<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

final class BatchProcessTelemetryStreamAction
{
    private EvaluateVitalsTelemetryAction $evaluator;

    public function __construct(EvaluateVitalsTelemetryAction $evaluator)
    {
        $this->evaluator = $evaluator;
    }

    /**
     * @param  list<array<string, mixed>>  $telemetryReadings
     * @return array{
     *     total_processed: int,
     *     alerts_triggered: int,
     *     results: list<array<string, mixed>>
     * }
     */
    public function execute(array $telemetryReadings): array
    {
        $results = [];
        $alertsCount = 0;

        foreach ($telemetryReadings as $reading) {
            $res = $this->evaluator->execute($reading);
            $results[] = $res;
            if (! empty($res['code_blue_triggered']) || (($res['news2_score'] ?? 0) >= 7)) {
                $alertsCount++;
            }
        }

        return [
            'total_processed' => count($telemetryReadings),
            'alerts_triggered' => $alertsCount,
            'results' => $results,
        ];
    }
}
