<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Exceptions;

use RuntimeException;

final class PeriodAlreadyLockedException extends RuntimeException
{
    public function __construct(string $entityCode, string $period)
    {
        parent::__construct(
            "Periode fiskal '{$period}' untuk entitas '{$entityCode}' telah dikunci permanen. Seluruh mutasi baru ditolak."
        );
    }
}
