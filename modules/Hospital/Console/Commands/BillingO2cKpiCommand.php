<?php

declare(strict_types=1);

namespace Modules\Hospital\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hospital\Application\Actions\Billing\GenerateO2cKpiReportAction;

class BillingO2cKpiCommand extends Command
{
    protected $signature = 'hsp:o2c-kpi
                            {--gross=10000000000 : Gross revenue in cents}
                            {--collected=9200000000 : Net collected in cents}
                            {--receivables=1500000000 : Total AR in cents}
                            {--total-claims=500 : Total submitted claims count}
                            {--clean-claims=460 : Clean first pass claims count}
                            {--denied-claims=25 : Denied claims count}';

    protected $description = 'Kompilasi KPI Revenue Cycle Management (RCM) dan Order-to-Cash (O2C) RS';

    public function handle(GenerateO2cKpiReportAction $action): int
    {
        $report = $action->execute(
            (int) $this->option('gross'),
            (int) $this->option('collected'),
            (int) $this->option('receivables'),
            (int) $this->option('total-claims'),
            (int) $this->option('clean-claims'),
            (int) $this->option('denied-claims')
        );

        $this->info('=== LAPORAN KPI REVENUE CYCLE MANAGEMENT RS ===');
        $this->table(
            ['Metrik', 'Nilai', 'Status Benchmark'],
            [
                ['Gross Revenue (IDR)', number_format($report['financial_summary']['gross_revenue_idr'], 2), 'OK'],
                ['Net Collected (IDR)', number_format($report['financial_summary']['net_collected_idr'], 2), 'OK'],
                ['Total AR (IDR)', number_format($report['financial_summary']['total_ar_idr'], 2), 'OK'],
                ['Days Sales Outstanding (DSO)', $report['rcm_kpi']['days_sales_outstanding_dso'] . ' Hari', $report['rcm_kpi']['dso_status']],
                ['Clean Claim Rate', $report['rcm_kpi']['clean_claim_rate_percent'] . '%', $report['rcm_kpi']['clean_claim_status']],
                ['Initial Denial Rate', $report['rcm_kpi']['denial_rate_percent'] . '%', $report['rcm_kpi']['denial_status']],
                ['Net Collection Rate', $report['rcm_kpi']['net_collection_rate_percent'] . '%', $report['rcm_kpi']['collection_status']],
            ]
        );

        return self::SUCCESS;
    }
}
