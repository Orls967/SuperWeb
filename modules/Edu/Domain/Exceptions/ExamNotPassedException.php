<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Exceptions;

use DomainException;
use Throwable;

final class ExamNotPassedException extends DomainException
{
    public function __construct(string $message = 'Exam attempt has not been passed; certificate cannot be issued', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
