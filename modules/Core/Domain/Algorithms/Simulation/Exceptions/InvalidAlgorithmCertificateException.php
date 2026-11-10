<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation\Exceptions;

use RuntimeException;

class InvalidAlgorithmCertificateException extends RuntimeException
{
    public function __construct(string $message = 'Sertifikat eksekusi algoritma tidak valid atau telah dimanipulasi.')
    {
        parent::__construct($message);
    }
}
