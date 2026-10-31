<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Exceptions;

use RuntimeException;
use Throwable;

final class CohortCapacityExceededException extends RuntimeException
{
    public function __construct(string $message = 'Cohort maximum capacity exceeded', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
