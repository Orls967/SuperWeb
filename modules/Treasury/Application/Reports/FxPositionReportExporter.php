<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Reports;

use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\Currency;
use Modules\Treasury\Domain\Models\ExchangeRate;

/**
 * Exporter Laporan Posisi Kas & Likuiditas Multi-Valas (CSV).
 */
final class FxPositionReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $asOfDate): array
    {
        $accounts = BankAccount::where('is_active', true)->orderBy('account_number')->get();
        $currencies = Currency::where('is_active', true)->pluck('minor_units', 'code');

        $rows = [];
        $rows[] = ['Account Number', 'Bank Name', 'Entity ID', 'Currency', 'Original Balance', 'Closing Rate IDR', 'Equivalent IDR', 'Type'];

        $totalEquivalentIdr = 0;

        foreach ($accounts as $acc) {
            $curr = $acc->currency;
            $minorUnits = $currencies[$curr] ?? 2;
            $scale = 10 ** $minorUnits;

            $rateRecord = ExchangeRate::where('from_currency', $curr)
                ->where('to_currency', 'IDR')
                ->whereDate('rate_date', '<=', $asOfDate)
                ->orderByDesc('rate_date')
                ->first();

            $rateNumerator = $rateRecord ? (int) $rateRecord->rate_numerator : ($curr === 'IDR' ? 1 : 15600);
            $rateDenominator = $rateRecord ? (int) $rateRecord->rate_denominator : 1;

            $balance = (int) $acc->balance;
            $equivalentIdr = ($curr === 'IDR')
                ? $balance
                : (int) intdiv($balance * $rateNumerator, $rateDenominator * $scale);

            $totalEquivalentIdr += $equivalentIdr;

            $rows[] = [
                $acc->account_number,
                $acc->bank_name,
                $acc->entity_id,
                $curr,
                number_format($balance, 0, ',', '.'),
                "{$rateNumerator}/{$rateDenominator}",
                number_format($equivalentIdr, 0, ',', '.'),
                $acc->is_petty_cash ? 'Petty Cash' : 'Operational Bank',
            ];
        }

        $rows[] = ['TOTAL', '', '', 'IDR', '', '', number_format($totalEquivalentIdr, 0, ',', '.'), ''];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "treasury-positions-{$asOfDate}.csv",
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
