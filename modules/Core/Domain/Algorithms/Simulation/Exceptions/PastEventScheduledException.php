<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation\Exceptions;

use RuntimeException;

class PastEventScheduledException extends RuntimeException
{
    public function __construct(int $scheduledTime, int $currentTime, string $eventName)
    {
        parent::__construct("PAST_EVENT: Tidak dapat menjadwalkan peristiwa '{$eventName}' pada waktu {$scheduledTime} karena waktu virtual saat ini adalah {$currentTime}.");
    }
}
