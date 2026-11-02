<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Exceptions;

use DomainException;
use Throwable;

final class SafetyPermitUnauthorizedException extends DomainException
{
    public function __construct(string $message = 'User lacks active safety certification for permit', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
