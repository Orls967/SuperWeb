<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Exceptions;

use Throwable;

class HotelRuleViolationException extends HotelDomainException
{
    public function __construct(string $message = 'Aturan operasional hotel dilanggar', int $code = 422, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
