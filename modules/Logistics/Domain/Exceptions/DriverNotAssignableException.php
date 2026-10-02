<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class DriverNotAssignableException extends RuntimeException
{
    public static function forDriver(string $driverNumber, string $reason): self
    {
        return new self("Pengemudi {$driverNumber} tidak dapat ditugaskan: {$reason}");
    }
}
