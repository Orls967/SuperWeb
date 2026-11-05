<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Exceptions;

final class AppendOnlyViolationException extends InsuranceDomainException
{
    public function __construct(string $model, string $operation)
    {
        parent::__construct("{$model} bersifat append-only; operasi {$operation} ditolak. Gunakan entri pembalik.");
    }
}
