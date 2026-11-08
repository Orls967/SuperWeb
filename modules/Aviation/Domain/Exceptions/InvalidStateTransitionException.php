<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Exceptions;

use RuntimeException;

final class InvalidStateTransitionException extends RuntimeException
{
    public static function make(string $entity, string $from, string $to): self
    {
        return new self("Transisi status tidak valid untuk {$entity} dari [{$from}] ke [{$to}].");
    }
}

