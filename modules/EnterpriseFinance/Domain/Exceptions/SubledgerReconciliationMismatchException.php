<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Exceptions;

use RuntimeException;

final class SubledgerReconciliationMismatchException extends RuntimeException
{
    public function __construct(string $entityCode, string $period, int $discrepancyMinor, string $module)
    {
        parent::__construct(
            "Tutup buku ditolak: Terdeteksi diskrepansi subledger modul '{$module}' sebesar Rp " . number_format($discrepancyMinor / 100, 2) . " pada entitas '{$entityCode}' periode '{$period}'."
        );
    }
}

