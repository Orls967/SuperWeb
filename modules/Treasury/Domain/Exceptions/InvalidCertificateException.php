<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Exceptions;

use RuntimeException;

class InvalidCertificateException extends RuntimeException
{
    public function __construct(string $message = 'Sertifikat algoritma rusak atau hash verifikasi tidak valid.')
    {
        parent::__construct($message);
    }
}
