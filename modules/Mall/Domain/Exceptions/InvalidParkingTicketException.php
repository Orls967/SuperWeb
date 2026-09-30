<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Exceptions;

use RuntimeException;

class InvalidParkingTicketException extends RuntimeException
{
    public function __construct(string $message = 'Tiket parkir tidak ditemukan atau tidak valid.')
    {
        parent::__construct($message);
    }
}
