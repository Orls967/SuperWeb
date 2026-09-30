<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use DomainException;

class NoRateCardFoundException extends DomainException
{
    public static function forRoute(int $originId, int $destinationId, string $serviceLevel): self
    {
        return new self("Tidak ditemukan rate card aktif untuk rute origin [{$originId}] ke destination [{$destinationId}] dengan level layanan [{$serviceLevel}].");
    }
}
