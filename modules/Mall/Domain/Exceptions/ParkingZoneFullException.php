<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Exceptions;

use RuntimeException;

class ParkingZoneFullException extends RuntimeException
{
    public function __construct(string $zoneName)
    {
        parent::__construct("Kapasitas area parkir '{$zoneName}' telah penuh. Akses masuk ditolak.");
    }
}
