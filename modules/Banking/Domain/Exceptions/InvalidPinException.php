<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Exceptions;

use RuntimeException;

class InvalidPinException extends RuntimeException
{
    public function __construct(public int $failedAttempts = 0, public int $remainingAttempts = 5)
    {
        parent::__construct("PIN salah. Sisa percobaan: {$remainingAttempts}.");
    }
}
