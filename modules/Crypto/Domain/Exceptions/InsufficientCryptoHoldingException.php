<?php

declare(strict_types=1);

namespace Modules\Crypto\Domain\Exceptions;

use RuntimeException;

class InsufficientCryptoHoldingException extends RuntimeException
{
    public function __construct(string $message = 'Saldo kripto tidak mencukupi untuk melakukan penjualan.')
    {
        parent::__construct($message);
    }
}
