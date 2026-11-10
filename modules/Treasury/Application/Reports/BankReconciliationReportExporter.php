<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Reports;

use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\BankReconciliationMatch;
use Modules\Treasury\Domain\Models\BankReconciliationUnmatched;
use Modules\Treasury\Domain\Models\BankStatement;

/**
 * Exporter Laporan Rekonsiliasi Bank 3-Tahap (Exact, Tolerance, Many-to-One).
 */
final class BankReconciliationReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $bankAccountId): array
    {
        $account = BankAccount::findOrFail($bankAccountId);
        $statements = BankStatement::where('bank_account_id', $bankAccountId)
            ->orderBy('transaction_date')
            ->get();

        $rows = [];
        $rows[] = [
            'Statement ID', 'Account Number', 'Tx Date', 'Reference No',
            'Amount', 'Status', 'Match Type', 'Matched Amount', 'Notes',
        ];

        $matchedCount = 0;
        $unmatchedCount = 0;
        $totalAmount = 0;

        foreach ($statements as $st) {
            $match = BankReconciliationMatch::where('statement_id', $st->id)->first();
            $unmatched = BankReconciliationUnmatched::where('statement_id', $st->id)->first();

            $matchType = $match ? $match->match_type : ($unmatched ? 'unmatched' : ($st->is_reconciled ? 'exact' : 'pending'));
            $matchedAmount = $match ? (string) $match->matched_amount : ($st->is_reconciled ? (string) $st->amount : '0');
            $notes = $match ? ($match->notes ?? '') : ($unmatched ? ($unmatched->reason ?? '') : '');

            if ($st->is_reconciled) {
                $matchedCount++;
            } else {
                $unmatchedCount++;
            }

            $totalAmount += (int) $st->amount;

            $rows[] = [
                $st->id,
                $account->account_number,
                $st->transaction_date ? $st->transaction_date->format('Y-m-d') : '',
                $st->reference_no,
                (string) $st->amount,
                $st->reconciliation_status,
                $matchType,
                $matchedAmount,
                $notes,
            ];
        }

        $rows[] = [
            'SUMMARY', "Total Lines: {$statements->count()}", "Matched: {$matchedCount}", "Unmatched: {$unmatchedCount}",
            (string) $totalAmount, '', '', '', '',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "treasury-reconciliation-{$account->account_number}.csv",
            'content' => $csv,
            'row_count' => count($rows) - 2,
            'sha256' => hash('sha256', $csv),
        ];
    }

    private function buildCsv(array $rows): string
    {
        $fp = fopen('php://memory', 'r+');
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $content = stream_get_contents($fp);
        fclose($fp);

        return $content ?: '';
    }
}
