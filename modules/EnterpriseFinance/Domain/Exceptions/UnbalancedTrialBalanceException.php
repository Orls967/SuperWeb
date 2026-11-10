<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Exceptions;

use RuntimeException;

class UnbalancedTrialBalanceException extends RuntimeException
{
    public function __construct(int $totalDebit, int $totalCredit, int $delta)
    {
        parent::__construct("Agregasi neraca saldo konsolidasi tidak seimbang: Total Debit ({$totalDebit}) != Total Kredit ({$totalCredit}), Selisih: {$delta}.");
    }
}
