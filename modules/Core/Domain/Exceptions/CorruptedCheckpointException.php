<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use RuntimeException;

class CorruptedCheckpointException extends RuntimeException
{
    public function __construct(string $message = 'Integritas rantai hash pada checkpoint simulasi terkorupsi atau tidak cocok.')
    {
        parent::__construct($message);
    }
}
