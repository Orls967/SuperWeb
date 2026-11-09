<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use Modules\Hospital\Application\Actions\Billing\GenerateO2cKpiReportAction;
use Modules\Hospital\Domain\Services\ClaimAgingAnalysisService;
use Modules\Hospital\Domain\Services\HospitalBillingVerticalAuditService;
use PHPUnit\Framework\TestCase;

class GenerateO2cKpiReportActionTest extends TestCase
{
    private GenerateO2cKpiReportAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GenerateO2cKpiReportAction(
            new HospitalBillingVerticalAuditService,
            new ClaimAgingAnalysisService
        );
    }

    public function test_generates_comprehensive_o2c_kpi_report(): void
    {
        $report = $this->action->execute(
            10_000_000_000, // Gross: 100jt IDR
            9_200_000_000,  // Net collected: 92jt IDR
            1_500_000_000,  // AR: 15jt IDR
            500,            // Total claims
            475,            // Clean claims (95%)
            15,             // Denied claims (3%)
            365
        );

        $this->assertSame(365, $report['period_days']);
        $this->assertSame(100_000_000.0, $report['financial_summary']['gross_revenue_idr']);
        $this->assertSame(92_000_000.0, $report['financial_summary']['net_collected_idr']);
        $this->assertSame(95.0, $report['rcm_kpi']['clean_claim_rate_percent']);
        $this->assertSame('EXCELLENT', $report['rcm_kpi']['clean_claim_status']);
        $this->assertSame(3.0, $report['rcm_kpi']['denial_rate_percent']);
        $this->assertSame('HEALTHY_LOW_DENIAL', $report['rcm_kpi']['denial_status']);
        $this->assertArrayHasKey('generated_at', $report);
    }
}
