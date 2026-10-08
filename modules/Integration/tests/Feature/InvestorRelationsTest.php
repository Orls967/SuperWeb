<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\InvestorRelationsService;
use Tests\TestCase;

/**
 * Fase 211 — Keuangan: Investor Relations & Market Discipline Tests
 *
 * Covers:
 *  (a) non-GAAP reconciliation bridge validates GAAP + reconciling items parity
 *  (b) non-GAAP bridge discrepancy throws exception
 *  (c) corporate action validates token supply conservation
 *  (d) group:audit = 0 discrepancy
 */
class InvestorRelationsTest extends TestCase
{
    use RefreshDatabase;

    protected InvestorRelationsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvestorRelationsService::class);
    }

    /**
     * (a) & (b) Non-GAAP reconciliation bridge.
     */
    public function test_nongaap_reconciliation_bridge(): void
    {
        // 1. Valid: Operating Profit 80M + Depreciation/Amort 20M = Adjusted EBITDA 100M -> SUCCESS
        $bridge = $this->service->recordNonGaapBridge('2026-Q3', 'ADJUSTED_EBITDA', 100000000.0, 80000000.0, 20000000.0);
        $this->assertSame('ADJUSTED_EBITDA', $bridge->metric_name);
        $this->assertEquals(0.00, (float) $bridge->bridge_discrepancy_idr);

        // 2. Discrepancy: Operating Profit 80M + Reconciling 15M != Reported 100M -> Exception
        try {
            $this->service->recordNonGaapBridge('2026-Q3', 'ADJUSTED_EBITDA', 100000000.0, 80000000.0, 15000000.0);
            $this->fail('Expected exception for non-GAAP bridge discrepancy.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Non-GAAP bridge discrepancy', $e->getMessage());
        }
    }

    /**
     * (c) Corporate action token conservation.
     */
    public function test_corporate_action_token_conservation(): void
    {
        // Pre 1,000,000 + Delta 500,000 == Post 1,500,000 -> SUCCESS
        $action = $this->service->executeCorporateAction('RIGHTS_ISSUE', 1000000.0, 500000.0, 1500000.0);
        $this->assertSame('RIGHTS_ISSUE', $action->action_type);
        $this->assertEquals(0.0000, (float) $action->balance_check_variance);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_investor_relations_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
