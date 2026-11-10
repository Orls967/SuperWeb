<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Reports;

use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;

/**
 * Exporter Laporan Matriks Rekonsiliasi Bilateral Intercompany (AR vs AP).
 * Menghasilkan rekapitulasi eksposur bilateral antar seluruh entitas grup,
 * mendeteksi saldo gantung (in-transit), serta menghitung saldo bersih eliminasi.
 */
final class IntercompanyMatrixReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $period): array
    {
        $entities = IntercompanyEntity::where('is_active', true)->orderBy('entity_code')->get();

        $rows = [];
        $rows[] = [
            'Period',
            'Selling / Creditor Entity',
            'Buying / Debtor Entity',
            'Currency',
            'AR Balance (Seller Book)',
            'AP Balance (Buyer Book)',
            'Net Discrepancy',
            'Status',
            'Pending Transactions Count',
            'Eliminated Amount IDR',
            'Last Reconciled At',
        ];

        $totalArIdr = 0;
        $totalApIdr = 0;
        $totalDiscrepancyIdr = 0;
        $matchedPairsCount = 0;
        $discrepancyPairsCount = 0;

        foreach ($entities as $seller) {
            foreach ($entities as $buyer) {
                if ($seller->id === $buyer->id) {
                    continue;
                }

                $txs = IntercompanyTransaction::where('selling_entity', $seller->entity_code)
                    ->where('buying_entity', $buyer->entity_code)
                    ->get();

                if ($txs->isEmpty()) {
                    continue;
                }

                $arSum = (int) $txs->whereIn('status', ['posted_seller', 'posted_buyer', 'reconciled'])->sum('amount_idr');
                $apSum = (int) $txs->whereIn('status', ['posted_buyer', 'reconciled'])->sum('amount_idr');
                $eliminatedSum = (int) $txs->where('status', 'eliminated')->sum('amount_idr');
                $pendingCount = $txs->whereIn('status', ['draft', 'submitted', 'arm_length_validated'])->count();

                $discrepancy = $arSum - $apSum;
                $status = ($discrepancy === 0) ? 'BALANCED' : 'DISCREPANCY';

                if ($status === 'BALANCED') {
                    $matchedPairsCount++;
                } else {
                    $discrepancyPairsCount++;
                }

                $totalArIdr += $arSum;
                $totalApIdr += $apSum;
                $totalDiscrepancyIdr += abs($discrepancy);

                $lastRecon = $txs->max('reconciled_at') ?? '-';

                $rows[] = [
                    $period,
                    $seller->entity_code.' ('.$seller->legal_name.')',
                    $buyer->entity_code.' ('.$buyer->legal_name.')',
                    'IDR',
                    (string) $arSum,
                    (string) $apSum,
                    (string) $discrepancy,
                    $status,
                    (string) $pendingCount,
                    (string) $eliminatedSum,
                    (string) $lastRecon,
                ];
            }
        }

        $rows[] = [
            'SUMMARY',
            'Total Pairs: '.(count($rows) - 1),
            "Matched: {$matchedPairsCount} | Discrepancies: {$discrepancyPairsCount}",
            'IDR',
            (string) $totalArIdr,
            (string) $totalApIdr,
            (string) $totalDiscrepancyIdr,
            ($totalDiscrepancyIdr === 0) ? 'FULLY_ALIGNED' : 'ACTION_REQUIRED',
            '',
            '',
            '',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "intercompany-bilateral-matrix-{$period}.csv",
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
