<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Exceptions;

use RuntimeException;

class TableOccupiedException extends RuntimeException
{
    public function __construct(string $message = 'Meja sedang digunakan atau tidak dalam status tersedia.')
    {
        parent::__construct($message);
    }
}
