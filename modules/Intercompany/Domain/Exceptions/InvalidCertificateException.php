<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Exceptions;

use RuntimeException;

class InvalidCertificateException extends RuntimeException
{
    public function __construct(string $message = 'Sertifikat hash SHA-256 tidak valid atau mengalami sabotase integritas data.')
    {
        parent::__construct($message);
    }
}
