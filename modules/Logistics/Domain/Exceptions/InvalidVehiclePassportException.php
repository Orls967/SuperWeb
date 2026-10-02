<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class InvalidVehiclePassportException extends RuntimeException
{
    public static function forPlate(string $plate, string $detail): self
    {
        return new self("Paspor digital kendaraan {$plate} tidak valid, penugasan ditolak. {$detail}");
    }
}
