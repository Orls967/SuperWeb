<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class ScheduleConflictException extends RuntimeException
{
    public static function forAsset(string $assetDescription, string $conflictingSchedule): self
    {
        return new self("Konflik jadwal: Aset '{$assetDescription}' telah ditugaskan pada jadwal lain '{$conflictingSchedule}' yang waktunya tumpang tindih.");
    }

    public static function forDriver(string $driverNumber, string $conflictingSchedule): self
    {
        return new self("Konflik jadwal: Pengemudi '{$driverNumber}' telah memiliki jadwal tugas lain '{$conflictingSchedule}' yang waktunya tumpang tindih.");
    }
}
