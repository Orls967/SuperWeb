<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Domain\Exceptions;

use DomainException;

final class IdempotencyConflictException extends DomainException
{
    public static function forKey(string $key): self
    {
        return new self("Idempotency-Key '{$key}' sudah dipakai dengan payload berbeda.");
    }
}
