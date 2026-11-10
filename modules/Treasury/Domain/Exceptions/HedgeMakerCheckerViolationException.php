<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Exceptions;

use RuntimeException;

class HedgeMakerCheckerViolationException extends RuntimeException
{
    public function __construct(string $message = 'Penyetuju kontrak lindung nilai tidak boleh sama dengan pembuat draf.')
    {
        parent::__construct($message);
    }
}
