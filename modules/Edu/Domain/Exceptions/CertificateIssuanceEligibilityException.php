<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Exceptions;

use DomainException;
use Throwable;

final class CertificateIssuanceEligibilityException extends DomainException
{
    public function __construct(string $message = 'Ineligible for certificate issuance', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
