<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Treasury\Domain\Models\BankReconciliationMatch;
use Modules\Treasury\Domain\Models\BankReconciliationUnmatched;
use Modules\Treasury\Domain\Models\BankStatement;

class UnmatchBankStatementAction
{
    /**
     * Membatalkan hasil pemadanan rekonsiliasi dan mengembalikan status ke unreconciled.
     */
    public function execute(BankStatement $statement, string $reason, ?int $userId = null): BankStatement
    {
        if (! $statement->is_reconciled && $statement->reconciliation_status === 'unreconciled') {
            throw new InvalidArgumentException("Mutasi bank {$statement->reference_no} belum pernah direkonsiliasi.");
        }

        return DB::transaction(function () use ($statement) {
            // Hapus match record jika ada
            BankReconciliationMatch::where('statement_id', $statement->id)->delete();

            // Tandai atau hapus unmatched record
            BankReconciliationUnmatched::where('statement_id', $statement->id)->delete();

            $statement->update([
                'is_reconciled' => false,
                'reconciliation_status' => 'unreconciled',
                'matched_ledger_tx_id' => null,
            ]);

            return $statement->fresh();
        });
    }
}
