<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\InvestorLenderReportingService;
use Tests\TestCase;

class InvestorLenderReportingTest extends TestCase
{
    use RefreshDatabase;

    protected InvestorLenderReportingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvestorLenderReportingService::class);
    }

    public function test_covenant_headroom_monitoring_and_disclosure_publishing(): void
    {
        // 439.1 & 439.2 Monitor Debt-to-EBITDA covenant with healthy headroom (3.5x max, 2.2x current -> ~37% headroom)
        $cov = $this->service->monitorCovenant(
            covenantCode: 'COV-BCA-SYNDICATE-01',
            facilityName: 'BCA Syndicated Term Loan 2026',
            metricName: 'Debt_to_EBITDA',
            maxThreshold: 3.50,
            currentValue: 2.20
        );

        $this->assertEquals(37.14, (float) $cov->headroom_percent);
        $this->assertFalse((bool) $cov->early_warning_triggered);
        $this->assertFalse((bool) $cov->is_in_breach);

        // 439.3 & 439.4 Create and publish quarterly investor disclosure report
        $this->service->createDisclosureReport('DISC-2026-Q3', 'Q3 2026 Consolidated Investor Report');

        $published = $this->service->publishDisclosure(
            code: 'DISC-2026-Q3',
            consistencyVerified: true,
            legalSignOff: true
        );

        $this->assertEquals('published', $published->status);
        $this->assertTrue((bool) $published->internal_consistency_verified);

        // 439.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_covenant_early_warning_and_inconsistent_disclosure_blocked_edge_cases(): void
    {
        // 439.5 Edge case: Covenant nearing threshold triggers early warning (< 15% headroom)
        // 3.0x max, 2.7x current -> (0.3 / 3.0) * 100 = 10% headroom (< 15%)
        $nearBreach = $this->service->monitorCovenant(
            covenantCode: 'COV-MANDIRI-WC',
            facilityName: 'Bank Mandiri Working Capital Facility',
            metricName: 'Leverage_Ratio',
            maxThreshold: 3.00,
            currentValue: 2.70
        );

        $this->assertEquals(10.00, (float) $nearBreach->headroom_percent);
        $this->assertTrue((bool) $nearBreach->early_warning_triggered);
        $this->assertFalse((bool) $nearBreach->is_in_breach);

        // 439.6 Risk: External disclosure differing from internal reports without verified consistency is blocked
        $this->service->createDisclosureReport('DISC-PRESS-RELEASE', 'Q3 Unaudited Revenue Press Release');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('External disclosure must pass reconciliation consistency check');

        $this->service->publishDisclosure(
            code: 'DISC-PRESS-RELEASE',
            consistencyVerified: false, // Inconsistent with internal ledger!
            legalSignOff: true
        );
    }
}
