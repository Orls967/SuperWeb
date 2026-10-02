<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class CarrierException extends RuntimeException
{
    public static function inactive(string $name): self
    {
        return new self("Carrier {$name} tidak aktif dan tidak dapat ditugaskan.");
    }

    public static function legClosed(int $legId): self
    {
        return new self("Leg #{$legId} sudah selesai atau dibatalkan; carrier dan biaya tidak dapat diubah.");
    }

    public static function invalidCost(): self
    {
        return new self('Biaya carrier harus lebih dari nol.');
    }
}
