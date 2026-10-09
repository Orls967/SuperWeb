<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\MiningPortCommodityFlowService;
use Tests\TestCase;

class MiningPortCommodityFlowTest extends TestCase
{
    use RefreshDatabase;

    protected MiningPortCommodityFlowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MiningPortCommodityFlowService::class);
    }

    public function test_mid_voyage_dispute_lot_isolation_edge_case(): void
    {
        // 1. Record commodity handoff (386.1 & 386.4)
        $this->service->recordChainHandoff(
            shipmentChainCode: 'CHN-COPPER-CONCENTRATE-01',
            lotId: 'LOT-COPPER-MINED-001',
            quantityTons: 25000.50
        );

        // 2. Mid-voyage assay dispute isolates lot & applies partial LC hold without disrupting other shipments (386.2, 386.4, 386.5 Edge Case)
        $isolated = $this->service->isolateAssayDisputeMidVoyage(
            shipmentChainCode: 'CHN-COPPER-CONCENTRATE-01',
            applyPartialLCHold: true
        );

        $this->assertTrue((bool) $isolated->is_lot_isolated);
        $this->assertTrue((bool) $isolated->lc_partial_hold_applied);
        $this->assertTrue((bool) $isolated->unaffected_shipments_proceed);
    }

    public function test_commodity_hedge_reconciliation_risk(): void
    {
        // 1. Hedged position exceeding physical exposure fails (386.3, 386.4, 386.6 Risk)
        try {
            $this->service->reconcileHedgePosition(
                hedgeCode: 'HDG-GOLD-FUTURES-01',
                physicalExposureTons: 50.00,
                hedgedPositionTons: 75.00 // 75 > 50 naked speculation!
            );
            $this->fail('Expected exception for over-hedged speculative position');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('creating naked speculative risk', $e->getMessage());
        }

        // 2. Balanced hedge position succeeds (386.3 & 386.4)
        $hedge = $this->service->reconcileHedgePosition(
            hedgeCode: 'HDG-GOLD-FUTURES-02',
            physicalExposureTons: 50.00,
            hedgedPositionTons: 50.00
        );
        $this->assertEquals(50.00, $hedge->hedged_position_tons);
        $this->assertTrue((bool) $hedge->over_hedged_prevented);
    }

    public function test_mining_and_port_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->recordChainHandoff('C-AUD', 'L-AUD', 1000.0);
        $this->service->reconcileHedgePosition('H-AUD', 1000.0, 800.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: over-hedged position
        DB::table('global_commodity_hedging_books')->insert([
            'hedge_code' => 'H-DEFECT-OVERHEDGED',
            'physical_exposure_tons' => 100.00,
            'hedged_position_tons' => 500.00, // Discrepancy!
            'over_hedged_prevented' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
