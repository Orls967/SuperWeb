<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Ai;

use Modules\Core\Domain\Exceptions\TransactionQuarantinedException;
use Modules\Core\Domain\Models\CoreAiAnomalyQuarantine;

class SettlementGuardService
{
    public function isSettlementAllowed(string $transactionRef): bool
    {
        return ! CoreAiAnomalyQuarantine::where('transaction_ref', $transactionRef)
            ->where('status', 'QUARANTINED')
            ->exists();
    }

    public function assertAllowed(string $transactionRef): void
    {
        if (! $this->isSettlementAllowed($transactionRef)) {
            throw new TransactionQuarantinedException(
                "Transaksi {$transactionRef} dalam status QUARANTINED. Settlement perbankan diblokir demi pencegahan fraud (D64-2)."
            );
        }
    }
}

