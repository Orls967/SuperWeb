<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Exceptions;

use RuntimeException;

class CovenantBreachException extends RuntimeException
{
    public function __construct(string $message = 'Penarikan fasilitas ditolak karena melanggar covenant bank.')
    {
        parent::__construct($message);
    }
}
