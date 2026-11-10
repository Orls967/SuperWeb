<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Reports;

use Modules\Treasury\Domain\Models\ForwardContract;

/**
 * Exporter Laporan Portofolio Lindung Nilai (Forward Contracts & MTM Valuation).
 */
final class HedgePortfolioReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(?string $status = null): array
    {
        $query = ForwardContract::query()->orderBy('maturity_date');

        if ($status !== null) {
            $query->where('status', $status);
        }

        $contracts = $query->get();

        $rows = [];
        $rows[] = [
            'Contract Number', 'Currency', 'Notional Amount', 'Forward Rate',
            'Maturity Date', 'MTM Value IDR', 'Status', 'Creator ID',
            'Settlement Gain/Loss IDR', 'Cancellation Fee IDR',
        ];

        $totalNotional = 0;
        $totalMtm = 0;
        $totalSettledGainLoss = 0;

        foreach ($contracts as $c) {
            $notional = (int) $c->notional_foreign_amount;
            $mtm = (int) $c->mtm_value_idr;
            $settled = (int) ($c->settlement_gain_loss_idr ?? 0);

            $totalNotional += $notional;
            $totalMtm += $mtm;
            $totalSettledGainLoss += $settled;

            $statusStr = is_object($c->status) && enum_exists($c->status::class) ? $c->status->value : (string) $c->status;

            $rows[] = [
                $c->contract_number,
                $c->currency,
                (string) $notional,
                "{$c->contract_rate_numerator}/{$c->contract_rate_denominator}",
                $c->maturity_date ? $c->maturity_date->format('Y-m-d') : '',
                (string) $mtm,
                $statusStr,
                $c->creator_id ? (string) $c->creator_id : '',
                (string) $settled,
                (string) ($c->cancellation_fee_idr ?? 0),
            ];
        }

        $rows[] = [
            'TOTAL', '', (string) $totalNotional, '', '',
            (string) $totalMtm, '', '', (string) $totalSettledGainLoss, '',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => 'treasury-hedge-portfolio.csv',
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
