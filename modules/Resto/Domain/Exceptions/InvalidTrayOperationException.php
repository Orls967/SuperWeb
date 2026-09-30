<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Exceptions;

use Exception;

class InvalidTrayOperationException extends Exception
{
    public function __construct(string $message = 'Operasi etalase tidak diizinkan atau melanggar aturan hidang.')
    {
        parent::__construct($message);
    }
}
