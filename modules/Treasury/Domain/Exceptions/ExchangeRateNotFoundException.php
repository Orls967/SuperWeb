<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Exceptions;

use RuntimeException;

class ExchangeRateNotFoundException extends RuntimeException
{
    public function __construct(string $from, string $to, string $date, string $type)
    {
        parent::__construct("Kurs dari {$from} ke {$to} untuk tanggal {$date} dan tipe {$type} tidak ditemukan.");
    }
}
