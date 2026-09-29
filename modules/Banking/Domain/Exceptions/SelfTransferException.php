<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Exceptions;

use RuntimeException;

class SelfTransferException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Tidak dapat melakukan transfer ke akun milik sendiri.');
    }
}
