<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Exceptions;

use RuntimeException;

class HedgeInvalidTransitionException extends RuntimeException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct("Transisi status kontrak lindung nilai dari {$from} ke {$to} tidak diizinkan.");
    }
}
