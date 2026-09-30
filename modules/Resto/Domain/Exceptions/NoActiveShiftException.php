<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Exceptions;

use RuntimeException;

class NoActiveShiftException extends RuntimeException
{
    public function __construct(string $message = 'Kasir harus membuka shift aktif sebelum menerima transaksi tunai.')
    {
        parent::__construct($message);
    }
}
