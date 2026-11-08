<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use DomainException;
use Illuminate\Support\Collection;
use Modules\Port\Domain\Models\FleetLedgerEntry;

/**
 * Service Double-Entry Fleet Ledger & Audit Keuangan Maritim.
 * Memastikan setiap transaksi finansial armada tercatat secara seimbang (Debit = Credit)
 * dan dilengkapi tanda tangan kriptografis sha256 checksum.
 */
final class PortFleetLedgerService
{
    public const ACC_CASH = '1000';
    public const ACC_RECEIVABLE_FREIGHT = '1200';
    public const ACC_PAYABLE_DISBURSEMENTS = '2100';
    public const ACC_REVENUE_FREIGHT = '4000';
    public const ACC_REVENUE_DEMURRAGE = '4100';
    public const ACC_EXPENSE_BUNKER = '5000';
    public const ACC_EXPENSE_PORT_DUES = '5100';
    public const ACC_EXPENSE_PILOT_TUG = '5200';
    public const ACC_EXPENSE_GENERAL_AVERAGE = '5300';

    /**
     * Catat transaksi berpasangan (double-entry pair).
     *
     * @return array{debit_entry: FleetLedgerEntry, credit_entry: FleetLedgerEntry}
     */
    public function recordBalancedTransaction(
        ?int $voyageId,
        ?int $vesselId,
        string $debitAccount,
        string $creditAccount,
        float $amount,
        string $referenceType,
        int $referenceId,
        string $description,
        string $currency = 'USD'
    ): array {
        if ($amount <= 0.0) {
            throw new DomainException('Nilai transaksi pembukuan harus bernilai positif.');
        }

        $codeBase = 'FLT-' . strtoupper(bin2hex(random_bytes(4)));
        $debitCode = "{$codeBase}-DR";
        $creditCode = "{$codeBase}-CR";

        $debitChecksum = FleetLedgerEntry::generateChecksum(
            $debitCode,
            $debitAccount,
            'DEBIT',
            $amount,
            $currency,
            $referenceType,
            $referenceId
        );

        $debitEntry = FleetLedgerEntry::create([
            'entry_code' => $debitCode,
            'voyage_id' => $voyageId,
            'vessel_id' => $vesselId,
            'account_code' => $debitAccount,
            'entry_type' => 'DEBIT',
            'amount' => $amount,
            'currency' => $currency,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => "{$description} (Debit)",
            'checksum' => $debitChecksum,
        ]);

        $creditChecksum = FleetLedgerEntry::generateChecksum(
            $creditCode,
            $creditAccount,
            'CREDIT',
            $amount,
            $currency,
            $referenceType,
            $referenceId
        );

        $creditEntry = FleetLedgerEntry::create([
            'entry_code' => $creditCode,
            'voyage_id' => $voyageId,
            'vessel_id' => $vesselId,
            'account_code' => $creditAccount,
            'entry_type' => 'CREDIT',
            'amount' => $amount,
            'currency' => $currency,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => "{$description} (Credit)",
            'checksum' => $creditChecksum,
        ]);

        return [
            'debit_entry' => $debitEntry,
            'credit_entry' => $creditEntry,
        ];
    }

    /**
     * Audit integritas pembukuan buku besar armada.
     *
     * @return array{
     *     total_entries: int,
     *     total_debit: float,
     *     total_credit: float,
     *     is_balanced: bool,
     *     corrupted_count: int,
     *     corrupted_ids: array<int, int>
     * }
     */
    public function auditLedgerIntegrity(?int $voyageId = null): array
    {
        $query = FleetLedgerEntry::query();
        if ($voyageId !== null) {
            $query->where('voyage_id', $voyageId);
        }

        $entries = $query->get();
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $corruptedIds = [];

        foreach ($entries as $entry) {
            if ($entry->entry_type === 'DEBIT') {
                $totalDebit += (float) $entry->amount;
            } else {
                $totalCredit += (float) $entry->amount;
            }

            if (! $entry->verifyChecksum()) {
                $corruptedIds[] = (int) $entry->id;
            }
        }

        $diff = abs($totalDebit - $totalCredit);
        $isBalanced = ($diff < 0.01);

        return [
            'total_entries' => $entries->count(),
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'is_balanced' => $isBalanced,
            'corrupted_count' => count($corruptedIds),
            'corrupted_ids' => $corruptedIds,
        ];
    }
}

