<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Reports;

use Modules\Intercompany\Domain\Models\EliminationEntry;

/**
 * Exporter Laporan Jurnal Eliminasi Konsolidasi Keuangan Grup (PSAK 65).
 * Memuat rincian setiap ayat jurnal eliminasi: eliminasi saldo resiprokal (AR vs AP),
 * transaksi penjualan internal (Sales vs COGS), laba persediaan belum terealisasi,
 * dividen antar-entitas, dan penyertaan modal saham anak perusahaan.
 */
final class EliminationEntriesReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $period): array
    {
        $entries = EliminationEntry::where('period', $period)
            ->orderBy('id')
            ->get();

        $rows = [];
        $rows[] = [
            'Period',
            'Elimination Code',
            'Elimination Type',
            'Source Entity',
            'Target Entity',
            'Debit Account Code',
            'Debit Amount IDR',
            'Credit Account Code',
            'Credit Amount IDR',
            'Netto Difference',
            'Description',
            'Ledger Tx Ref',
            'Created At',
        ];

        $totalDebitIdr = 0;
        $totalCreditIdr = 0;

        foreach ($entries as $e) {
            $debit = (int) $e->debit_amount_idr;
            $credit = (int) $e->credit_amount_idr;
            $diff = $debit - $credit;

            $totalDebitIdr += $debit;
            $totalCreditIdr += $credit;

            $rows[] = [
                $e->period,
                $e->elimination_code,
                $e->elimination_type,
                $e->source_entity,
                $e->target_entity,
                $e->debit_account_code,
                (string) $debit,
                $e->credit_account_code,
                (string) $credit,
                (string) $diff,
                $e->description,
                (string) ($e->ledger_transaction_id ?? '-'),
                (string) $e->created_at,
            ];
        }

        $totalBalanceDiff = $totalDebitIdr - $totalCreditIdr;

        $rows[] = [
            'SUMMARY',
            "Total Entries: {$entries->count()}",
            '',
            '',
            '',
            'Total Debit IDR',
            (string) $totalDebitIdr,
            'Total Credit IDR',
            (string) $totalCreditIdr,
            (string) $totalBalanceDiff,
            ($totalBalanceDiff === 0) ? 'BALANCED_NETTO_ZERO' : 'UNBALANCED_ERROR',
            '',
            '',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "consolidation-elimination-entries-{$period}.csv",
            'content' => $csv,
            'row_count' => count($rows) - 2,
            'sha256' => hash('sha256', $csv),
        ];
    }

    /**
     * @param  list<list<string>>  $rows
     */
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
