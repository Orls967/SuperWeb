<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Exceptions;

use RuntimeException;

class ShiftAlreadyOpenException extends RuntimeException
{
    public function __construct(string $message = 'Kasir masih memiliki shift aktif yang belum ditutup.')
    {
        parent::__construct($message);
    }
}
