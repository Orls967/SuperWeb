<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class CapacityCutoffExceededException extends RuntimeException
{
    public static function forSchedule(string $scheduleNumber, string $cutoffAt): self
    {
        return new self("Waktu batas pemesanan kargo (cut-off) untuk jadwal '{$scheduleNumber}' telah terlampaui pada {$cutoffAt}.");
    }
}
