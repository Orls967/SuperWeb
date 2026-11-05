<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Exceptions;

final class IdempotencyKeyConflictException extends InsuranceDomainException
{
    public function __construct(string $key)
    {
        parent::__construct("Idempotency-Key '{$key}' sudah dipakai dengan isi permintaan berbeda.");
    }
}
