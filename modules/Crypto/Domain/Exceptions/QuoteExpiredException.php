<?php

declare(strict_types=1);

namespace Modules\Crypto\Domain\Exceptions;

use RuntimeException;

class QuoteExpiredException extends RuntimeException
{
    public function __construct(string $message = 'Kuotasi harga telah kedaluwarsa. Silakan perbarui harga terkini.')
    {
        parent::__construct($message);
    }
}
