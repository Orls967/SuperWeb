<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\CrowdSafety;

use InvalidArgumentException;

/**
 * Nilai Masukan Algoritma V14 CrowdDensitySurgePredictor.
 */
final readonly class CrowdPredictionInput
{
    public function __construct(
        public int $zoneId,
        public int $currentHeadcount,
        public int $zoneAreaSqm,
        public int $legalCapacityLimit,
        public int $inflowVelocityPerMinute,
        public int $outflowVelocityPerMinute,
        public int $weatherFactorBps = 10000
    ) {
        if ($this->zoneId <= 0) {
            throw new InvalidArgumentException('Zone ID harus bernilai positif integer.');
        }
        if ($this->zoneAreaSqm <= 0) {
            throw new InvalidArgumentException('Luas zona meter persegi harus bernilai lebih besar dari nol.');
        }
        if ($this->legalCapacityLimit <= 0) {
            throw new InvalidArgumentException('Batas kapasitas legal harus lebih besar dari nol.');
        }
    }
}
