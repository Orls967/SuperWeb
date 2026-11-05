<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Exceptions;

final class InvalidStatusTransitionException extends InsuranceDomainException
{
    public function __construct(string $entity, string $from, string $to)
    {
        parent::__construct("Transisi status {$entity} dari '{$from}' ke '{$to}' tidak diizinkan.");
    }
}
