<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class CapacityExceededException extends RuntimeException
{
    public static function forSchedule(string $scheduleNumber, string $reason): self
    {
        return new self("Kapasitas jadwal '{$scheduleNumber}' tidak mencukupi: {$reason}");
    }
}
