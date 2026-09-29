<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Exceptions;

use RuntimeException;

class AccountFrozenException extends RuntimeException
{
    public function __construct(string $accountCode)
    {
        parent::__construct("Akun '{$accountCode}' sedang dibekukan (frozen) dan tidak dapat melakukan transaksi.");
    }
}
