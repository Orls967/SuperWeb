<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\CapitalFundingStrategyService;
use Tests\TestCase;

/**
 * Fase 210 — Keuangan: Capital Management & Funding Strategy Tests
 *
 * Covers:
 *  (a) covenant monitoring detects debt/equity limit breach
 *  (b) distribution allowed when within retained profit
 *  (c) distribution rejected when exceeding available retained profit
 *  (d) treasury:audit = 0 discrepancy
 */
class CapitalFundingStrategyTest extends TestCase
{
    use RefreshDatabase;

    protected CapitalFundingStrategyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CapitalFundingStrategyService::class);
    }

    /**
     * (a) Debt covenant monitoring.
     */
    public function test_debt_covenant_monitoring(): void
    {
        // 1. Within threshold: DER 1.8x <= 2.5x -> Clean
        $c1 = $this->service->monitorCovenant('ENT-AIRLINE', 'DEBT_TO_EQUITY', 2.5, 1.8);
        $this->assertFalse((bool) $c1->covenant_breached);

        // 2. Breached threshold: DER 3.2x > 2.5x -> Breached
        $c2 = $this->service->monitorCovenant('ENT-AIRLINE', 'DEBT_TO_EQUITY', 2.5, 3.2);
        $this->assertTrue((bool) $c2->covenant_breached);
    }

    /**
     * (b) & (c) Solvency and retained profit distribution validation.
     */
    public function test_capital_distribution_solvency_test(): void
    {
        // 1. Valid: Proposed 10 Billion from 35 Billion profit -> APPROVED
        $d1 = $this->service->proposeDistribution('ENT-HOLDING', 35000000000.0, 10000000000.0);
        $this->assertSame('APPROVED', $d1->approval_status);
        $this->assertTrue((bool) $d1->solvency_test_passed);

        // 2. Invalid: Proposed 40 Billion from 35 Billion profit -> Exception
        try {
            $this->service->proposeDistribution('ENT-HOLDING', 35000000000.0, 40000000000.0);
            $this->fail('Expected exception for excessive distribution.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds available profit', $e->getMessage());
        }
    }

    /**
     * (d) Audit status healthy with 0 discrepancies (when covenants compliant).
     */
    public function test_capital_funding_audit(): void
    {
        DB::table('fin_capital_covenants')->truncate();
        $this->service->monitorCovenant('ENT-HOLDING', 'DEBT_TO_EQUITY', 2.5, 1.5);

        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
