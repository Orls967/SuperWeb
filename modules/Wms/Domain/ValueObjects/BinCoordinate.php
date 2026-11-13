<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Koordinat fisik bin dalam layout gudang (zona, lorong/aisle, rak, level, posisi).
 */
final readonly class BinCoordinate
{
    public function __construct(
        public string $zoneCode,
        public int $aisle,
        public int $rack,
        public int $level,
        public int $position,
    ) {
        if ($zoneCode === '' || $aisle < 1 || $rack < 1 || $level < 1 || $position < 0) {
            throw new InvalidArgumentException('Koordinat bin tidak valid.');
        }
    }

    public function manhattanDistanceTo(self $other, int $aisleWidth = 10, int $levelPenalty = 2): int
    {
        $aisleDiff = abs($this->aisle - $other->aisle) * $aisleWidth;
        $posDiff = abs($this->position - $other->position);
        $levelDiff = abs($this->level - $other->level) * $levelPenalty;

        return $aisleDiff + $posDiff + $levelDiff;
    }

    public function toCode(): string
    {
        return sprintf('%s-A%02d-R%02d-L%02d-P%03d', strtoupper($this->zoneCode), $this->aisle, $this->rack, $this->level, $this->position);
    }
}
