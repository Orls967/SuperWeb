<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

use InvalidArgumentException;

/**
 * Evaluator kapasitas bin berdasarkan volume, berat, dan jumlah unit base (integer murni).
 */
final class BinCapacityEvaluator
{
    /**
     * @return array{
     *     fits: bool,
     *     unit_headroom: int,
     *     weight_headroom_g: int,
     *     volume_headroom_cm3: int,
     *     limiting_factor: string
     * }
     */
    public function evaluateFit(
        int $currentUnits,
        int $maxUnits,
        int $currentWeightG,
        int $maxWeightG,
        int $currentVolumeCm3,
        int $maxVolumeCm3,
        int $incomingUnits,
        int $unitWeightG,
        int $unitVolumeCm3,
    ): array {
        if ($incomingUnits <= 0 || $maxUnits <= 0 || $maxWeightG <= 0 || $maxVolumeCm3 <= 0) {
            throw new InvalidArgumentException('Parameter evaluasi kapasitas bin tidak valid.');
        }

        $unitHeadroom = $maxUnits - ($currentUnits + $incomingUnits);
        $weightHeadroom = $maxWeightG - ($currentWeightG + ($incomingUnits * $unitWeightG));
        $volumeHeadroom = $maxVolumeCm3 - ($currentVolumeCm3 + ($incomingUnits * $unitVolumeCm3));

        $limiting = 'none';
        if ($unitHeadroom < 0) {
            $limiting = 'units';
        } elseif ($weightHeadroom < 0) {
            $limiting = 'weight';
        } elseif ($volumeHeadroom < 0) {
            $limiting = 'volume';
        }

        return [
            'fits' => $unitHeadroom >= 0 && $weightHeadroom >= 0 && $volumeHeadroom >= 0,
            'unit_headroom' => $unitHeadroom,
            'weight_headroom_g' => $weightHeadroom,
            'volume_headroom_cm3' => $volumeHeadroom,
            'limiting_factor' => $limiting,
        ];
    }
}
