<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Exceptions;

use DomainException;
use Throwable;

abstract class HotelDomainException extends DomainException
{
    public function __construct(string $message = '', int $code = 422, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
