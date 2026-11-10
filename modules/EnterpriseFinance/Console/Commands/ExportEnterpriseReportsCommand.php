<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Console\Commands;

use Illuminate\Console\Command;
use Modules\EnterpriseFinance\Application\Reports\ConsolidatedTrialBalanceReportExporter;
use Modules\EnterpriseFinance\Application\Reports\DepartmentBudgetVarianceReportExporter;
use Modules\EnterpriseFinance\Application\Reports\ExecutiveConsolidatedFinancialSummaryReportExporter;
use Modules\EnterpriseFinance\Application\Reports\RegulatoryComplianceCalendarReportExporter;
use Modules\EnterpriseFinance\Application\Reports\SegregationOfDutiesMatrixReportExporter;
use Modules\EnterpriseFinance\Application\Reports\SyndicatedLoanCovenantComplianceReportExporter;
use Modules\EnterpriseFinance\Application\Reports\ThirteenWeekCashLiquidityForecastReportExporter;
use Modules\EnterpriseFinance\Application\Reports\WorkingCapitalOptimizationReportExporter;

class ExportEnterpriseReportsCommand extends Command
{
    protected $signature = 'ef:export-reports {--period= : Periode laporan (YYYY-MM)} {--year= : Tahun fiskal (YYYY)} {--dir=storage/reports/enterprise : Direktori penyimpanan CSV}';

    protected $description = 'Mengekspor seluruh 8 laporan keuangan korporat ke format CSV bersertifikat SHA-256';

    public function handle(): int
    {
        $period = (string) ($this->option('period') ?: date('Y-m'));
        $year = (string) ($this->option('year') ?: date('Y'));
        $dir = (string) $this->option('dir');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->info("Mengekspor 8 Laporan Keuangan Grup untuk periode {$period} / {$year}...");

        $exporters = [
            '1. Consolidated Trial Balance' => (new ConsolidatedTrialBalanceReportExporter)->export($period),
            '2. Budget Variance Analysis' => (new DepartmentBudgetVarianceReportExporter)->export($year),
            '3. Syndicated Loan Covenants' => (new SyndicatedLoanCovenantComplianceReportExporter)->export($period),
            '4. 13-Week Cash Liquidity' => (new ThirteenWeekCashLiquidityForecastReportExporter)->export(date('Y-m-d')),
            '5. Working Capital Optimization' => (new WorkingCapitalOptimizationReportExporter)->export($period),
            '6. Segregation of Duties Matrix' => (new SegregationOfDutiesMatrixReportExporter)->export(),
            '7. Compliance Calendar' => (new RegulatoryComplianceCalendarReportExporter)->export($year),
            '8. Executive Financial Summary' => (new ExecutiveConsolidatedFinancialSummaryReportExporter)->export($period),
        ];

        foreach ($exporters as $label => $data) {
            $path = "{$dir}/{$data['filename']}";
            file_put_contents($path, $data['content']);
            $this->line("{$label} -> {$data['filename']} ({$data['row_count']} baris) SHA-256: ".substr($data['sha256'], 0, 16).'...');
        }

        $this->info("Seluruh 8 laporan berhasil diekspor ke direktori {$dir}.");

        return self::SUCCESS;
    }
}
