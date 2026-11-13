<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use InvalidArgumentException;
use Modules\Wms\Domain\Certificates\BatteryDegradationCertificate;

/**
 * Pelacak degradasi kapasitas baterai robot berbasis siklus pengisian (integer permil, tanpa float).
 *
 * Model degradasi deterministik:
 * - Kapasitas awal = 1_000 permil (100.0%).
 * - Setiap 10 siklus pengisian menurunkan kapasitas sebesar 1 permil.
 * - Setiap kejadian deep-discharge (< 15%) menurunkan kapasitas tambahan 3 permil.
 * - Ambang rekomendasi penggantian pak baterai: health_permil <= 750 (75.0%).
 */
final class RobotBatteryDegradationTracker
{
    public const int INITIAL_HEALTH_PERMIL = 1_000;

    public const int CYCLES_PER_PERMIL_LOSS = 10;

    public const int DEEP_DISCHARGE_PENALTY_PERMIL = 3;

    public const int REPLACEMENT_THRESHOLD_PERMIL = 750;

    /**
     * @param  list<array{robot_id:int, cycle_count:int, deep_discharge_events:int}>  $fleetMetrics
     */
    public function evaluateFleet(array $fleetMetrics): BatteryDegradationCertificate
    {
        $rows = [];
        $sumHealth = 0;
        $replaceCount = 0;

        foreach ($fleetMetrics as $metric) {
            $robotId = (int) $metric['robot_id'];
            $cycles = max(0, (int) $metric['cycle_count']);
            $deepDischarges = max(0, (int) $metric['deep_discharge_events']);

            if ($robotId <= 0) {
                throw new InvalidArgumentException('robot_id harus bernilai positif.');
            }

            $cycleLoss = intdiv($cycles, self::CYCLES_PER_PERMIL_LOSS);
            $deepLoss = $deepDischarges * self::DEEP_DISCHARGE_PENALTY_PERMIL;
            $healthPermil = max(0, self::INITIAL_HEALTH_PERMIL - $cycleLoss - $deepLoss);
            $replaceRecommended = $healthPermil <= self::REPLACEMENT_THRESHOLD_PERMIL;

            if ($replaceRecommended) {
                $replaceCount++;
            }

            $sumHealth += $healthPermil;
            $rows[] = [
                'robot_id' => $robotId,
                'cycle_count' => $cycles,
                'health_permil' => $healthPermil,
                'replace_recommended' => $replaceRecommended,
            ];
        }

        $total = count($rows);
        $avgHealth = $total > 0 ? intdiv($sumHealth, $total) : 0;
        $hash = BatteryDegradationCertificate::computeHash($total, $replaceCount, $avgHealth, $rows);

        $cert = new BatteryDegradationCertificate(
            totalRobots: $total,
            replacementCount: $replaceCount,
            fleetAvgHealthPermil: $avgHealth,
            robots: $rows,
            signatureHash: $hash,
        );
        $cert->verify();

        return $cert;
    }
}
