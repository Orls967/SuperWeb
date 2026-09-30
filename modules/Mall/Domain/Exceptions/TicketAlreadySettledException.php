<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Exceptions;

use RuntimeException;

class TicketAlreadySettledException extends RuntimeException
{
    public function __construct(string $ticketNumber)
    {
        parent::__construct("Tiket parkir '{$ticketNumber}' sudah selesai diproses / keluar.");
    }
}
