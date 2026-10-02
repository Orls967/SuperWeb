<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class TruckNotAssignableException extends RuntimeException
{
    public static function forTruck(string $plate, string $reason): self
    {
        return new self("Truk {$plate} tidak dapat ditugaskan: {$reason}");
    }
}
