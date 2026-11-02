<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Exceptions;

use Throwable;

class IdempotencyConflictException extends HotelDomainException
{
    public function __construct(string $message = 'Idempotency key digunakan ulang dengan payload yang berbeda', int $code = 409, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
