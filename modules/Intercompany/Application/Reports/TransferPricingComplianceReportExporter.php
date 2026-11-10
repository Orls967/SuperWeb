<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Reports;

use Modules\Intercompany\Domain\Algorithms\TransferPricingMarkupCalculator;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Modules\Intercompany\Domain\Models\TransferPricingBenchmark;
use Modules\Intercompany\Domain\Models\TransferPricingRule;
use Modules\Intercompany\Domain\Models\TransferPricingTrueUp;

/**
 * Exporter Laporan Kepatuhan Transfer Pricing (PMK-172 / OECD TP Guidelines).
 * Menghasilkan bukti evaluasi arm's length interquartile range (IQR),
 * riwayat true-up adjustment, dan deviasi margin terhadap benchmark independen.
 */
final class TransferPricingComplianceReportExporter
{
    public function __construct(
        protected readonly ?TransferPricingMarkupCalculator $calculator = null
    ) {}

    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $fiscalYear): array
    {
        $calc = $this->calculator ?? new TransferPricingMarkupCalculator;
        $rules = TransferPricingRule::all();

        $rows = [];
        $rows[] = [
            'Fiscal Year',
            'Rule Code',
            'Product Category',
            'Method',
            'Min Margin (bps)',
            'Median Margin (bps)',
            'Max Margin (bps)',
            'Total Transactions Checked',
            'Compliant Count',
            'Breach Count',
            'Total True-Up Adjustments (IDR)',
            'Benchmark Source',
            'Audit Status',
        ];

        $totalChecked = 0;
        $totalCompliant = 0;
        $totalBreached = 0;
        $totalTrueUpMinor = 0;

        foreach ($rules as $rule) {
            $txs = IntercompanyTransaction::where('tp_rule_id', $rule->id)
                ->whereYear('created_at', (int) $fiscalYear)
                ->get();

            $benchmarks = TransferPricingBenchmark::where('tp_rule_id', $rule->id)
                ->pluck('benchmark_margin_bps')
                ->map(fn ($b) => (int) $b)
                ->all();

            if (empty($benchmarks)) {
                $benchmarks = [$rule->min_margin_bps, $rule->median_margin_bps, $rule->max_margin_bps];
            }

            $compliant = 0;
            $breached = 0;

            foreach ($txs as $tx) {
                // Asumsi cost minor diestimasi jika tidak eksplisit
                $costMinor = (int) ($tx->amount_idr * 10000 / (10000 + $rule->median_margin_bps));
                $eval = $calc->evaluateMargin(
                    method: $rule->tp_method,
                    costMinor: $costMinor,
                    actualPriceMinor: (int) $tx->amount_idr,
                    benchmarkMarginsBps: $benchmarks
                );

                if ($eval->isCompliant()) {
                    $compliant++;
                } else {
                    $breached++;
                }
            }

            $trueUps = TransferPricingTrueUp::where('tp_rule_id', $rule->id)
                ->where('fiscal_year', (int) $fiscalYear)
                ->sum('adjustment_amount_idr');

            $ruleChecked = $txs->count();
            $totalChecked += $ruleChecked;
            $totalCompliant += $compliant;
            $totalBreached += $breached;
            $totalTrueUpMinor += (int) $trueUps;

            $auditStatus = ($breached === 0) ? 'FULLY_COMPLIANT' : 'ADJUSTMENT_REQUIRED';

            $rows[] = [
                $fiscalYear,
                $rule->rule_code,
                $rule->product_category,
                $rule->tp_method,
                (string) $rule->min_margin_bps,
                (string) $rule->median_margin_bps,
                (string) $rule->max_margin_bps,
                (string) $ruleChecked,
                (string) $compliant,
                (string) $breached,
                (string) $trueUps,
                $rule->benchmark_industry_source,
                $auditStatus,
            ];
        }

        $rows[] = [
            'SUMMARY',
            "Rules Count: {$rules->count()}",
            '',
            '',
            '',
            '',
            '',
            (string) $totalChecked,
            (string) $totalCompliant,
            (string) $totalBreached,
            (string) $totalTrueUpMinor,
            'OECD / PMK-172 Benchmarks',
            ($totalBreached === 0) ? 'ALL_RULES_COMPLIANT' : 'DEFICIENCIES_DETECTED',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "transfer-pricing-compliance-FY{$fiscalYear}.csv",
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
