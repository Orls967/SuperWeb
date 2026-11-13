<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Spesifikasi batasan wave picking outbound.
 */
final readonly class PickingWaveSpec
{
    public function __construct(
        public string $waveCode,
        public int $maxOrders,
        public int $maxLines,
        public int $maxWeightGrams,
        public int $maxVolumeCm3,
    ) {
        if ($waveCode === '' || $maxOrders <= 0 || $maxLines <= 0 || $maxWeightGrams <= 0 || $maxVolumeCm3 <= 0) {
            throw new InvalidArgumentException('Spesifikasi wave picking tidak valid.');
        }
    }

    public function canAccommodate(int $currentOrders, int $currentLines, int $currentWeightG, int $currentVolumeCm3): bool
    {
        return $currentOrders <= $this->maxOrders
            && $currentLines <= $this->maxLines
            && $currentWeightG <= $this->maxWeightGrams
            && $currentVolumeCm3 <= $this->maxVolumeCm3;
    }
}
