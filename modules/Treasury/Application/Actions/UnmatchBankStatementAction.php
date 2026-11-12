<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Treasury\Domain\Models\BankReconciliationMatch;
use Modules\Treasury\Domain\Models\BankReconciliationUnmatched;
use Modules\Treasury\Domain\Models\BankStatement;
use Psr\Clock\ClockInterface;

class UnmatchBankStatementAction
{
    public function __construct(
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Membatalkan hasil pemadanan rekonsiliasi dengan alasan dan jejak audit (D48-5),
     * lalu mengembalikan status mutasi ke unreconciled / antrean unmatched.
     */
    public function execute(BankStatement $statement, string $reason, ?int $userId = null): BankStatement
    {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new InvalidArgumentException('Alasan pembatalan rekonsiliasi (unmatch reason) wajib diisi untuk jejak audit.');
        }

        if (! $statement->is_reconciled && $statement->reconciliation_status === 'unreconciled') {
            throw new InvalidArgumentException("Mutasi bank {$statement->reference_no} belum pernah direkonsiliasi.");
        }

        return DB::transaction(function () use ($statement, $cleanReason, $userId) {
            $now = $this->now();

            // Hapus match record jika ada
            BankReconciliationMatch::where('statement_id', $statement->id)->delete();

            // Tandai atau hapus unmatched record lama
            BankReconciliationUnmatched::where('statement_id', $statement->id)->delete();

            $statement->update([
                'is_reconciled' => false,
                'reconciliation_status' => 'unreconciled',
                'unmatch_reason' => $cleanReason,
                'unmatched_by' => $userId,
                'unmatched_at' => $now,
                'matched_ledger_tx_id' => null,
            ]);

            return $statement->fresh();
        });
    }
}
