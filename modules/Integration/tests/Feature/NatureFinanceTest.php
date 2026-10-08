<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\NatureFinanceService;
use Tests\TestCase;

/**
 * Fase 173 — Nature Finance, Biodiversity & Ecosystem Services Tests
 *
 * Covers:
 *  (a) issued credits <= verified outcomes enforced
 *  (b) retired credits cannot be resold or re-retired
 *  (c) benefit share sums strictly to total proceeds
 *  (d) nature:audit = 0 discrepancy
 */
class NatureFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected NatureFinanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NatureFinanceService::class);
    }

    /**
     * (a) Issued credits bounded by verified outcome units.
     */
    public function test_credit_issuance_bounded_by_outcomes(): void
    {
        $project = $this->service->registerProject('Sumatra Peatland Watershed', 'VCS-VM0007', 5.0);

        // 1. Issue 3 credits (<= 5.0) -> OK
        $credits = $this->service->issueCredits($project->project_code, 3.0, 'HOLDING-CORP-A');
        $this->assertCount(3, $credits);

        // 2. Request 4 more credits (remaining 2.0) -> Exception
        $this->expectException(\RuntimeException::class);
        $this->service->issueCredits($project->project_code, 4.0, 'HOLDING-CORP-A');
    }

    /**
     * (b) Retired credits cannot be re-retired or resold.
     */
    public function test_retired_credit_cannot_be_re_retired(): void
    {
        $project = $this->service->registerProject('Rimbang Baling Corridor', 'CCB-GOLD', 2.0);
        $credits = $this->service->issueCredits($project->project_code, 1.0, 'AIRLINE-CORP');
        $serial = $credits[0];

        // First retirement -> OK
        $retired = $this->service->retireCredit($serial);
        $this->assertTrue((bool) $retired->is_retired);
        $this->assertNotNull($retired->retired_at);

        // Second retirement -> Exception
        $this->expectException(\RuntimeException::class);
        $this->service->retireCredit($serial);
    }

    /**
     * (c) Benefit share math sums to total proceeds.
     */
    public function test_benefit_share_sums_to_proceeds(): void
    {
        $share = $this->service->distributeBenefitShare('PRJ-001', 1000000000.00, 35.0); // 1 Billion, 35% community

        $this->assertEquals(350000000.00, (float) $share->community_disbursement_idr);
        $this->assertEquals(650000000.00, (float) $share->developer_share_idr);
        $this->assertTrue((bool) $share->math_sums_to_proceeds);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_nature_finance_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
