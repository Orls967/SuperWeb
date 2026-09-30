<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class ActiveLoadConflictException extends RuntimeException
{
    public static function forUnit(string $unitDescription, string $conflictingLoadNumber): self
    {
        return new self("Unit kargo '{$unitDescription}' sedang aktif pada muatan '{$conflictingLoadNumber}' dan tidak boleh berada di dua load aktif sekaligus.");
    }
}
