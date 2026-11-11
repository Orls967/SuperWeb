<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use DomainException;

/**
 * Thrown when an API v2 mutation reuses an Idempotency-Key with a different payload digest (W8-D46).
 */
final class ApiIdempotencyConflictException extends DomainException
{
    public static function forKey(string $key): self
    {
        return new self("IDEMPOTENCY_CONFLICT: Kunci idempotensi [{$key}] telah dipakai dengan isi permintaan berbeda.");
    }
}

