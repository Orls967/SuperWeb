<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\FleetAssetUtilizationOptimizationService;
use Tests\TestCase;

class FleetAssetUtilizationOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected FleetAssetUtilizationOptimizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FleetAssetUtilizationOptimizationService::class);
    }

    public function test_asset_utilization_and_net_contribution_evaluation(): void
    {
        // 1. High operating hours with high revenue (278.1 & 278.5)
        // 160h op, 40h idle @ $50/h = $2,000 idle cost; $25,000 revenue => net contribution $23,000
        $metric = $this->service->recordAssetUtilization(
            assetCode: 'VESSEL-NICKEL-01',
            assetCategory: 'CARGO_VESSEL',
            operatingHours: 160.0,
            idleHours: 40.0,
            generatedRevenueUsd: 25000.0,
            idleHourlyCostUsd: 50.0
        );

        $this->assertEquals(80.0, (float) $metric->utilization_pct);
        $this->assertEquals(2000.0, (float) $metric->idle_cost_usd);
        $this->assertEquals(23000.0, (float) $metric->net_contribution_usd); // Net contribution primary metric (278.5)
    }

    public function test_asset_allocation_enforces_maintenance_and_crew_constraints(): void
    {
        // 1. Maintenance & crew cleared succeeds (278.2 & 278.4)
        $alloc = $this->service->allocateAsset(
            assetCode: 'CRANE-DOCK-04',
            demandContractRef: 'CTR-EXPORT-MINING-09',
            maintenanceCleared: true,
            crewCertifiedCleared: true
        );
        $this->assertEquals('ALLOCATED', $alloc->status);

        // 2. Missing maintenance clearance rejected (278.2)
        try {
            $this->service->allocateAsset('CRANE-DOCK-04', 'CTR-RUSH', maintenanceCleared: false, crewCertifiedCleared: true);
            $this->fail('Expected exception for uncleared maintenance');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Maintenance clearance or certified crew requirements not satisfied', $e->getMessage());
        }

        // 3. Missing certified crew rejected (278.2)
        try {
            $this->service->allocateAsset('CRANE-DOCK-04', 'CTR-RUSH', maintenanceCleared: true, crewCertifiedCleared: false);
            $this->fail('Expected exception for uncertified crew');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Maintenance clearance or certified crew requirements not satisfied', $e->getMessage());
        }
    }

    public function test_repair_or_replace_tco_and_budget_encumbrance_guard(): void
    {
        // 1. TCO repair-or-replace recommendation (278.3)
        // Replacement $500,000 - Salvage $100,000 = $400,000 net; 60% threshold is $240,000
        // Cumulative repairs $300,000 >= $240,000 => REPLACE_NEW_ASSET
        $decision = $this->service->evaluateRepairOrReplace(
            assetCode: 'HAUL-TRUCK-KOMATSU-99',
            cumulativeRepairCostUsd: 300000.0,
            replacementCostUsd: 500000.0,
            salvageValueUsd: 100000.0,
            budgetEncumbered: false // Unencumbered!
        );
        $this->assertEquals('REPLACE_NEW_ASSET', $decision->recommended_action);

        // 2. Unencumbered capex approval rejected (278.6 Edge Case)
        try {
            $this->service->approveCapexReplacement($decision->decision_code);
            $this->fail('Expected exception for unencumbered capex');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Budget encumbrance required prior to financing sign-off', $e->getMessage());
        }

        // 3. Encumbered capex approval succeeds (278.6)
        $encumberedDecision = $this->service->evaluateRepairOrReplace(
            assetCode: 'HAUL-TRUCK-KOMATSU-100',
            cumulativeRepairCostUsd: 350000.0,
            replacementCostUsd: 500000.0,
            salvageValueUsd: 100000.0,
            budgetEncumbered: true
        );
        $approved = $this->service->approveCapexReplacement($encumberedDecision->decision_code);
        $this->assertTrue((bool) $approved->is_capex_approved);
    }

    public function test_fleet_asset_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->recordAssetUtilization('AST-AUD', 'TRUCK', 10.0, 2.0, 1000.0);
        $this->service->allocateAsset('AST-AUD', 'CTR-1', true, true);
        $dec = $this->service->evaluateRepairOrReplace('AST-AUD', 100.0, 1000.0, 200.0, true);
        $this->service->approveCapexReplacement($dec->decision_code);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: approved capex without budget encumbrance
        DB::table('asset_lifecycle_decisions')->insert([
            'decision_code' => 'DEC-BREACH-UNENCUMBERED',
            'asset_code' => 'AST-AUD',
            'cumulative_repair_cost_usd' => 500.0,
            'estimated_replacement_cost_usd' => 1000.0,
            'residual_salvage_value_usd' => 100.0,
            'recommended_action' => 'REPLACE_NEW_ASSET',
            'is_budget_encumbered' => false, // Discrepancy!
            'is_capex_approved' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
