<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Reports;

use Modules\Treasury\Domain\Models\Revaluation;

/**
 * Exporter Laporan Revaluasi Kurs Akhir Periode (PSAK 10 & Pembalik Otomatis).
 */
final class FxRevaluationReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $period): array
    {
        $revals = Revaluation::where('period', $period)->orderBy('currency')->get();

        $rows = [];
        $rows[] = [
            'Period', 'Currency', 'Foreign Balance', 'Book Functional IDR',
            'Revalued Functional IDR', 'Gain/Loss IDR', 'Status', 'Is Reversed',
            'Ledger Tx ID', 'Certificate Hash',
        ];

        $totalGainLossIdr = 0;
        $totalBookIdr = 0;
        $totalRevaluedIdr = 0;

        foreach ($revals as $r) {
            $gainLoss = (int) $r->gain_loss_idr;
            $book = (int) $r->book_functional_idr;
            $revalued = (int) $r->revalued_functional_idr;

            $totalGainLossIdr += $gainLoss;
            $totalBookIdr += $book;
            $totalRevaluedIdr += $revalued;

            $cert = $r->certificate;
            $certHash = is_array($cert) ? ($cert['sha256'] ?? '') : '';

            $rows[] = [
                $r->period,
                $r->currency,
                (string) $r->foreign_balance,
                (string) $book,
                (string) $revalued,
                (string) $gainLoss,
                $r->status,
                $r->is_reversed ? 'YES' : 'NO',
                $r->ledger_transaction_id ? (string) $r->ledger_transaction_id : '',
                $certHash,
            ];
        }

        $rows[] = [
            'TOTAL', '', '', (string) $totalBookIdr,
            (string) $totalRevaluedIdr, (string) $totalGainLossIdr, '', '', '', '',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "treasury-revaluation-{$period}.csv",
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
