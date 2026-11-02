<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Exceptions;

use DomainException;
use Throwable;

final class HeadhunterWarrantyNotExpiredException extends DomainException
{
    public function __construct(string $message = 'Headhunter warranty period has not yet expired', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
