<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Exceptions;

use RuntimeException;

class BudgetMakerCheckerViolationException extends RuntimeException
{
    public function __construct(string $message = 'Pelanggaran prinsip Four-Eyes: Pengesahan anggaran tidak boleh dilakukan oleh pembuat usulan.')
    {
        parent::__construct($message);
    }
}
