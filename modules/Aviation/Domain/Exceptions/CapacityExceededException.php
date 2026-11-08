<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Exceptions;

use RuntimeException;

final class CapacityExceededException extends RuntimeException
{
    public static function forFlightCabin(int|string $flightId, string $cabin, int $requested, int $available): self
    {
        return new self("Kapasitas kursi kabin [{$cabin}] pada penerbangan #{$flightId} tidak mencukupi (minta: {$requested}, tersedia: {$available}).");
    }
}

