<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Exceptions;

use RuntimeException;

class InvalidCertificateException extends RuntimeException
{
    public function __construct(string $message = 'Sertifikat digital finansial tidak valid atau mengalami pemalsuan tanda tangan.')
    {
        parent::__construct($message);
    }
}
