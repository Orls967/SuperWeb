<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use DomainException;

class NoRateBracketFoundException extends DomainException
{
    public static function forWeight(int $rateCardId, float $weightKg): self
    {
        return new self("Tidak ditemukan bracket tarif pada rate card #{$rateCardId} untuk berat {$weightKg} kg.");
    }
}
