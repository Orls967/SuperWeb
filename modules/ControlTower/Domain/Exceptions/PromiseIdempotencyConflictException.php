<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Exceptions;

use RuntimeException;

final class PromiseIdempotencyConflictException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Konflik idempotency key '{$key}' untuk payload kuotasi ATP/CTP yang berbeda.");
    }
}
