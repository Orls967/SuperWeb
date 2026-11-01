<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Exceptions;

use DomainException;
use Throwable;

final class PrerequisiteCycleException extends DomainException
{
    public function __construct(string $message = 'Cycle detected in curriculum prerequisites', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
