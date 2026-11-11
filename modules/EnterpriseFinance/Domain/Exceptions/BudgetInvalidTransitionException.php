<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Exceptions;

use RuntimeException;

class BudgetInvalidTransitionException extends RuntimeException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct("Transisi siklus anggaran tidak sah dari status [{$from}] ke [{$to}].");
    }
}
