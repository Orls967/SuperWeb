<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\EndToEndSupplyChainService;
use Tests\TestCase;

class EndToEndSupplyChainTest extends TestCase
{
    use RefreshDatabase;

    protected EndToEndSupplyChainService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EndToEndSupplyChainService::class);
    }

    public function test_plan_to_serve_cycle_metrics_and_tradeoff_inconsistency_guard(): void
    {
        // 1. Balanced normal cycle (251.1)
        $normalCycle = $this->service->recordPlanToServeCycle(
            batchCycleCode: 'SCM-2026-W40',
            otifRatePct: 94.50,
            daysOfSupply: 32.0,
            cashToCashDays: 45.0,
            previousOtif: 93.0,
            previousC2c: 44.0
        );

        $this->assertFalse((bool) $normalCycle->has_tradeoff_inconsistency);
        $this->assertNull($normalCycle->tradeoff_review_notes);

        // 2. Inconsistent trade-off cycle: OTIF jumped from 91% to 98%, but C2C degraded from 35 to 65 days (251.5 Edge Case)
        $inconsistentCycle = $this->service->recordPlanToServeCycle(
            batchCycleCode: 'SCM-2026-W41',
            otifRatePct: 98.20,
            daysOfSupply: 55.0,
            cashToCashDays: 65.0,
            previousOtif: 91.0,
            previousC2c: 35.0
        );

        $this->assertTrue((bool) $inconsistentCycle->has_tradeoff_inconsistency);
        $this->assertNotNull($inconsistentCycle->tradeoff_review_notes);
        $this->assertStringContainsString('TRADE-OFF ALERT', $inconsistentCycle->tradeoff_review_notes);
    }

    public function test_control_tower_disruption_reporting_and_optimizer_mitigation(): void
    {
        $disruption = $this->service->reportDisruptionAndExecuteMitigation(
            originDomain: 'PORT',
            severity: 'HIGH',
            blastRadiusNodes: 14,
            mitigationResolutionNotes: 'Rerouted 12 container vessels to alternative deep-water berths; inventory re-allocated via network optimizer.'
        );

        $this->assertEquals('PORT', $disruption->origin_domain);
        $this->assertEquals('HIGH', $disruption->severity);
        $this->assertEquals(14, (int) $disruption->blast_radius_affected_nodes_count);
        $this->assertTrue((bool) $disruption->mitigation_plan_executed);
        $this->assertNotNull($disruption->mitigation_resolution_notes);
    }

    public function test_cost_to_serve_per_order_and_esg_green_shipping(): void
    {
        // 1. Standard shipping (251.3 & 251.7)
        $standardOrder = $this->service->recordOrderCostToServe(
            orderCode: 'ORD-CHAIN-01',
            manufactureCost: 4000.0,
            moveTransportCost: 1500.0,
            sellCommercialCost: 800.0,
            serviceSupportCost: 200.0,
            baseCo2EmissionsKg: 500.0,
            isGreenShippingOpted: false
        );

        $this->assertEquals(6500.0, (float) $standardOrder->total_chain_cost_usd);
        $this->assertEquals(500.0, (float) $standardOrder->co2_emissions_kg);

        // 2. Green shipping reduces net emissions by 40% (251.7)
        $greenOrder = $this->service->recordOrderCostToServe(
            orderCode: 'ORD-CHAIN-02',
            manufactureCost: 4000.0,
            moveTransportCost: 1700.0, // slight green premium
            sellCommercialCost: 800.0,
            serviceSupportCost: 200.0,
            baseCo2EmissionsKg: 500.0,
            isGreenShippingOpted: true
        );

        $this->assertEquals(6700.0, (float) $greenOrder->total_chain_cost_usd);
        $this->assertEquals(300.0, (float) $greenOrder->co2_emissions_kg); // 500 * 0.60 = 300
    }

    public function test_supply_chain_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->recordPlanToServeCycle('SCM-AUD-OK', 95.0, 30.0, 40.0);
        $this->service->reportDisruptionAndExecuteMitigation('MINING', 'MEDIUM', 3, 'Mitigated');
        $this->service->recordOrderCostToServe('ORD-AUD-1', 100.0, 50.0, 30.0, 20.0, 50.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unmitigated disruption
        DB::table('supply_chain_control_tower_disruptions')->insert([
            'disruption_code' => 'DISRUPT-ROGUE',
            'origin_domain' => 'ENERGY',
            'severity' => 'CRITICAL',
            'blast_radius_affected_nodes_count' => 10,
            'mitigation_plan_executed' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
