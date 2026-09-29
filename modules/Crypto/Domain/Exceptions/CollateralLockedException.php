<?php

declare(strict_types=1);

namespace Modules\Crypto\Domain\Exceptions;

use RuntimeException;

class CollateralLockedException extends RuntimeException
{
    public function __construct(string $message = 'Sebagian atau seluruh aset ini sedang dikunci sebagai kolateral pembiayaan.')
    {
        parent::__construct($message);
    }
}
