<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\ProfitabilityCostIntelligenceService;
use Tests\TestCase;

/**
 * Fase 212 — Keuangan: Profitability, Transfer Pricing & Cost Intelligence Tests
 *
 * Covers:
 *  (a) profitability hierarchy verifies Σ unit profits equals entity total profit
 *  (b) profitability hierarchy throws exception if discrepancy exists
 *  (c) margin bridge balances sum of variance drivers against actual margin change
 *  (d) group:audit = 0 discrepancy
 */
class ProfitabilityCostIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected ProfitabilityCostIntelligenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProfitabilityCostIntelligenceService::class);
    }

    /**
     * (a) & (b) Profitability hierarchy tests.
     */
    public function test_profitability_hierarchy_reconciliation(): void
    {
        // 1. Valid: Entity Profit 100M == Unit A (40M) + Unit B (35M) + Unit C (25M) -> SUCCESS
        $h = $this->service->recordProfitabilityHierarchy('ENT-COMMERCE', 100000000.0, [40000000.0, 35000000.0, 25000000.0]);
        $this->assertEquals(0.00, (float) $h->hierarchy_discrepancy_idr);
        $this->assertEquals(100000000.00, (float) $h->aggregated_units_profit_idr);

        // 2. Discrepancy: Entity Profit 100M != Sum (90M) -> Exception
        try {
            $this->service->recordProfitabilityHierarchy('ENT-COMMERCE', 100000000.0, [40000000.0, 30000000.0, 20000000.0]);
            $this->fail('Expected exception for hierarchy discrepancy.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Profitability hierarchy discrepancy', $e->getMessage());
        }
    }

    /**
     * (c) Margin bridge variance drivers balance.
     */
    public function test_margin_bridge_balance(): void
    {
        // Delta +50M = Vol (+20M) + Mix (+10M) + Price (+15M) + Cost (+10M) + FX (-5M)
        $mb = $this->service->recordMarginBridge('2026-M09', 20000000.0, 10000000.0, 15000000.0, 10000000.0, -5000000.0, 50000000.0);
        $this->assertEquals(0.00, (float) $mb->unexplained_bridge_variance_idr);
        $this->assertEquals(50000000.00, (float) $mb->total_actual_margin_delta_idr);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_profitability_cost_intelligence_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
