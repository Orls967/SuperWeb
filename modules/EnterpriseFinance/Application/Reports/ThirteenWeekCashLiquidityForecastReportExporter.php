<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Reports;

/**
 * Exporter Laporan Proyeksi Likuiditas Kas Korporat 13-Minggu (13-Week Cash Forecast).
 * Memuat proyeksi arus kas masuk operasional, kas keluar komitmen, layanan utang sindikasi,
 * belanja modal (CAPEX), dan posisi saldo akhir likuiditas bersih per minggu.
 */
final class ThirteenWeekCashLiquidityForecastReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $startDate): array
    {
        $rows = [];
        $rows[] = [
            'Week Number',
            'Forecast Date',
            'Beginning Cash Minor',
            'Operating Inflows Minor',
            'Payroll & Supplier Outflows Minor',
            'Debt Service Outflows Minor',
            'Capex Outflows Minor',
            'Net Cash Change Minor',
            'Ending Cash Minor',
            'Safety Buffer Status',
        ];

        // Simulasi 13 minggu ke depan berbasis baseline korporat
        $runningCash = 250_000_000_000; // Baseline kas 250 Miliar
        $safetyThreshold = 50_000_000_000; // Buffer minimum 50 Miliar

        $start = strtotime($startDate) ?: time();

        for ($week = 1; $week <= 13; $week++) {
            $forecastDate = date('Y-m-d', $start + (($week - 1) * 7 * 86400));
            $beginning = $runningCash;

            // Arus kas operasional mingguan
            $inflows = 35_000_000_000 + (($week % 4 === 0) ? 15_000_000_000 : 0);
            $operatingOutflows = 20_000_000_000 + (($week % 2 === 0) ? 5_000_000_000 : 0);
            $debtOutflows = ($week % 4 === 0) ? 8_000_000_000 : 1_500_000_000;
            $capexOutflows = ($week === 6 || $week === 12) ? 12_000_000_000 : 2_000_000_000;

            $totalOutflows = $operatingOutflows + $debtOutflows + $capexOutflows;
            $netChange = $inflows - $totalOutflows;
            $ending = $beginning + $netChange;
            $runningCash = $ending;

            $status = ($ending < $safetyThreshold) ? 'LIQUIDITY_WARNING' : 'ADEQUATE';

            $rows[] = [
                "Week {$week}",
                $forecastDate,
                (string) $beginning,
                (string) $inflows,
                (string) $operatingOutflows,
                (string) $debtOutflows,
                (string) $capexOutflows,
                (string) $netChange,
                (string) $ending,
                $status,
            ];
        }

        $rows[] = [
            'SUMMARY',
            '13-Week Forecast Horizon',
            'Baseline: 250B IDR',
            '',
            '',
            '',
            '',
            'Final Projected Cash',
            (string) $runningCash,
            ($runningCash >= $safetyThreshold) ? 'HEALTHY_LIQUIDITY_RUNWAY' : 'ACTION_REQUIRED',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "13-week-cash-liquidity-forecast-{$startDate}.csv",
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
