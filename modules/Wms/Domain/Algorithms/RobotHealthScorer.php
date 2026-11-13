<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

/**
 * Penilai kesehatan armada robot berbasis skor integer 0..1000 dan aturan anomali berversi (X279-2).
 *
 * Kompleksitas: O(1) per sampel telemetri.
 * Aritmetika: 100% bilangan bulat (tanpa float).
 */
final class RobotHealthScorer
{
    public const int MAX_SCORE = 1000;

    public const int ANOMALY_THRESHOLD_SCORE = 650;

    /**
     * @return array{
     *     health_score: int,
     *     is_anomaly: bool,
     *     anomaly_code: string|null,
     *     parameter_version: string
     * }
     */
    public function score(int $batteryPct, int $errorCount, int $downtimeMinutes): array
    {
        $batteryClamped = max(0, min(100, $batteryPct));
        $batteryPenalty = (100 - $batteryClamped) * 4;
        $errorPenalty = min(400, max(0, $errorCount) * 80);
        $downtimePenalty = min(300, max(0, $downtimeMinutes) * 5);

        $score = max(0, self::MAX_SCORE - $batteryPenalty - $errorPenalty - $downtimePenalty);

        $anomalyCode = null;
        if ($batteryClamped <= 15) {
            $anomalyCode = 'CRITICAL_LOW_BATTERY';
        } elseif ($errorCount >= 3) {
            $anomalyCode = 'HIGH_ERROR_BURST';
        } elseif ($downtimeMinutes >= 30) {
            $anomalyCode = 'EXCESSIVE_DOWNTIME';
        } elseif ($score < self::ANOMALY_THRESHOLD_SCORE) {
            $anomalyCode = 'DEGRADED_FLEET_HEALTH';
        }

        return [
            'health_score' => $score,
            'is_anomaly' => $anomalyCode !== null,
            'anomaly_code' => $anomalyCode,
            'parameter_version' => 'v1-asumsi_simulasi',
        ];
    }
}
