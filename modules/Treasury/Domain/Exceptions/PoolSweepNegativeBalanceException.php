<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Exceptions;

use RuntimeException;

class PoolSweepNegativeBalanceException extends RuntimeException
{
    public function __construct(string $message = 'Penyapuan pooling kas ditolak karena menyebabkan saldo rekening anak menjadi negatif.')
    {
        parent::__construct($message);
    }
}
