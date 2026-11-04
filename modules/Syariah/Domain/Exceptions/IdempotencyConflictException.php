<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Exceptions;

use RuntimeException;

final class IdempotencyConflictException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Idempotency-Key '{$key}' sudah digunakan dengan payload yang berbeda.");
    }
}
