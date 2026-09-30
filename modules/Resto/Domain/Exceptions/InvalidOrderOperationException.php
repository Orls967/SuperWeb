<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Exceptions;

use RuntimeException;

class InvalidOrderOperationException extends RuntimeException
{
    public function __construct(string $message = 'Operasi pesanan tidak valid.')
    {
        parent::__construct($message);
    }
}
