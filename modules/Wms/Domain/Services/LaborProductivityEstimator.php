<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

use InvalidArgumentException;

/**
 * Estimator kebutuhan jam kerja operator gudang berdasarkan volume tugas & standar detik per aksi.
 */
final class LaborProductivityEstimator
{
    private const array SECONDS_PER_UNIT = [
        'putaway' => 45,
        'pick' => 35,
        'pack' => 25,
        'cycle_count' => 60,
        'replenish' => 50,
    ];

    /**
     * @param  array<string, int>  $taskQuantitiesByKind
     * @return array{
     *     total_seconds: int,
     *     total_minutes: int,
     *     shifts_required: int,
     *     breakdown_seconds: array<string, int>
     * }
     */
    public function estimate(array $taskQuantitiesByKind, int $minutesPerShift = 420): array
    {
        if ($minutesPerShift <= 0) {
            throw new InvalidArgumentException('Durasi shift harus lebih dari 0 menit.');
        }

        $totalSeconds = 0;
        $breakdown = [];

        foreach ($taskQuantitiesByKind as $kind => $qty) {
            $normalizedKind = strtolower(trim($kind));
            $rate = self::SECONDS_PER_UNIT[$normalizedKind] ?? 40;
            $sec = max(0, (int) $qty) * $rate;
            $breakdown[$normalizedKind] = $sec;
            $totalSeconds += $sec;
        }

        $totalMinutes = intdiv($totalSeconds + 59, 60);
        $shifts = $totalMinutes === 0 ? 0 : intdiv($totalMinutes + $minutesPerShift - 1, $minutesPerShift);

        return [
            'total_seconds' => $totalSeconds,
            'total_minutes' => $totalMinutes,
            'shifts_required' => $shifts,
            'breakdown_seconds' => $breakdown,
        ];
    }
}
