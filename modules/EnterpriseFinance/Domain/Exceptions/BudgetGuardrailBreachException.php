<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Exceptions;

use RuntimeException;

class BudgetGuardrailBreachException extends RuntimeException
{
    public function __construct(string $message = 'Pagu anggaran tidak mencukupi untuk memenuhi transaksi belanja (Hard-Stop aktif).')
    {
        parent::__construct($message);
    }
}
