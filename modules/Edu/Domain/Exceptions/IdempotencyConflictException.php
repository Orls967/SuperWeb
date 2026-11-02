<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Exceptions;

use DomainException;
use Throwable;

final class IdempotencyConflictException extends DomainException
{
    public function __construct(string $message = 'Idempotency key reused with conflicting payload', int $code = 409, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
