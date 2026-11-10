<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Reports;

use Modules\Treasury\Domain\Models\CashPoolSweep;

/**
 * Exporter Laporan Riwayat Eksekusi Cash Pool Sweeps (CSV).
 */
final class CashPoolSweepsReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(?string $poolId = null): array
    {
        $query = CashPoolSweep::with('pool')->orderByDesc('sweep_date');

        if ($poolId !== null) {
            $query->where('pool_id', $poolId);
        }

        $sweeps = $query->get();

        $rows = [];
        $rows[] = [
            'Sweep ID', 'Pool Name', 'Sweep Date', 'Swept Amount IDR', 'Direction',
            'Header Before IDR', 'Header After IDR', 'Sub Before IDR', 'Sub After IDR', 'Ledger Tx ID',
        ];

        $totalSweptIdr = 0;

        foreach ($sweeps as $sw) {
            $swept = (int) $sw->swept_amount_idr;
            $totalSweptIdr += $swept;

            $rows[] = [
                $sw->id,
                $sw->pool ? $sw->pool->pool_name : 'Pool #'.$sw->pool_id,
                $sw->sweep_date ? $sw->sweep_date->format('Y-m-d') : '',
                (string) $swept,
                $sw->direction,
                (string) $sw->header_balance_before,
                (string) $sw->header_balance_after,
                (string) $sw->sub_balance_before,
                (string) $sw->sub_balance_after,
                $sw->ledger_tx_id ? (string) $sw->ledger_tx_id : '',
            ];
        }

        $rows[] = [
            'TOTAL SWEPT', '', '', (string) $totalSweptIdr, '',
            '', '', '', '', '',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => 'treasury-cash-pool-sweeps.csv',
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
