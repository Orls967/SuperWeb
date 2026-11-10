<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Reports;

use Modules\Treasury\Domain\Models\CashForecast;

/**
 * Exporter Laporan Peramalan Arus Kas 13-Minggu Musiman OLS (CSV).
 */
final class CashForecast13WeekReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $scenario = 'base'): array
    {
        $forecasts = CashForecast::where('scenario', $scenario)
            ->orderBy('forecast_week')
            ->get();

        $rows = [];
        $rows[] = [
            'Scenario', 'Week Number', 'Week Start Date', 'Opening Balance',
            'Projected Inflow', 'Projected Outflow', 'Net Cash Flow', 'Closing Balance',
            'OLS Intercept', 'OLS Slope',
        ];

        $totalInflow = 0;
        $totalOutflow = 0;

        foreach ($forecasts as $f) {
            $inflow = (int) $f->projected_inflow;
            $outflow = (int) $f->projected_outflow;
            $net = $inflow - $outflow;

            $totalInflow += $inflow;
            $totalOutflow += $outflow;

            $rows[] = [
                $f->scenario,
                (string) $f->forecast_week,
                $f->week_start_date ? $f->week_start_date->format('Y-m-d') : '',
                (string) $f->opening_balance,
                (string) $inflow,
                (string) $outflow,
                (string) $net,
                (string) $f->closing_balance,
                (string) $f->ols_intercept,
                (string) $f->ols_slope,
            ];
        }

        $rows[] = [
            'TOTAL 13 WEEKS', '', '', '',
            (string) $totalInflow, (string) $totalOutflow, (string) ($totalInflow - $totalOutflow), '', '', '',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "treasury-cash-forecast-13w-{$scenario}.csv",
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
