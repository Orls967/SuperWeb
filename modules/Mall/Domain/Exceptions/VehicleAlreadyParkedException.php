<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Exceptions;

use RuntimeException;

class VehicleAlreadyParkedException extends RuntimeException
{
    public function __construct(string $plateNumber)
    {
        parent::__construct("Kendaraan dengan plat nomor '{$plateNumber}' masih tercatat parkir aktif di dalam area mall.");
    }
}
