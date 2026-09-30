<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class InvalidLoadConsolidationException extends RuntimeException
{
    public static function fclMultipleShipments(string $loadNumber): self
    {
        return new self("Muatan FCL '{$loadNumber}' hanya mengizinkan tepat 1 shipment per kontainer.");
    }

    public static function dgIncompatible(string $loadNumber, string $newClass, string $existingClass): self
    {
        return new self("Pelanggaran segregasi DG pada muatan '{$loadNumber}': Kelas DG {$newClass} tidak boleh dimuat bersama Kelas DG {$existingClass} (IMDG Code).");
    }

    public static function reeferMismatch(string $loadNumber, string $reason): self
    {
        return new self("Pelanggaran muatan dingin (reefer) pada '{$loadNumber}': {$reason}");
    }
}
