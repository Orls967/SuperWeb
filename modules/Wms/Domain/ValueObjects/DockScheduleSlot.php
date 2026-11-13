<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Slot waktu appointment dock bay dalam menit sejak 00:00 (0..1440).
 */
final readonly class DockScheduleSlot
{
    public function __construct(
        public string $dockBay,
        public int $startMinute,
        public int $endMinute,
        public string $direction = 'inbound',
    ) {
        if ($dockBay === '' || $startMinute < 0 || $endMinute > 1440 || $endMinute <= $startMinute) {
            throw new InvalidArgumentException('Slot waktu dock tidak valid.');
        }
        if (! in_array($direction, ['inbound', 'outbound'], true)) {
            throw new InvalidArgumentException("Arah dock tidak valid: {$direction}");
        }
    }

    public function overlaps(self $other): bool
    {
        return $this->dockBay === $other->dockBay
            && $this->startMinute < $other->endMinute
            && $this->endMinute > $other->startMinute;
    }

    public function durationMinutes(): int
    {
        return $this->endMinute - $this->startMinute;
    }
}
