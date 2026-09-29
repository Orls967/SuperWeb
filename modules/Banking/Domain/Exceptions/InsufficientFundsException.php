<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Exceptions;

use RuntimeException;

class InsufficientFundsException extends RuntimeException
{
    public function __construct(string $accountCode, string $currentBalance, string $requestedAmount)
    {
        parent::__construct("Saldo tidak mencukupi pada akun '{$accountCode}'. Saldo saat ini: {$currentBalance}, jumlah diminta: {$requestedAmount}.");
    }
}
