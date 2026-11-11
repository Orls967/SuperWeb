<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Exceptions;

use RuntimeException;

class UnauthorizedApprovalTierException extends RuntimeException
{
    public function __construct(int $amountMinor, int $tierLimitMinor, string $tierRole)
    {
        parent::__construct("Nilai transaksi belanja ({$amountMinor}) melampaui batas kewenangan peran {$tierRole} (Maksimal: {$tierLimitMinor}).");
    }
}
