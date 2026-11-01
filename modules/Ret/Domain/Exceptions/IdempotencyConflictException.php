<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Exceptions;

use DomainException;

class IdempotencyConflictException extends DomainException
{
    public function __construct(string $key)
    {
        parent::__construct(
            "Idempotency conflict detected for key [{$key}]: payload does not match existing record."
        );
    }
}
