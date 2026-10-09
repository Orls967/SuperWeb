<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SupplierCollaborativeSourcingService;
use Tests\TestCase;

class SupplierCollaborativeSourcingTest extends TestCase
{
    use RefreshDatabase;

    protected SupplierCollaborativeSourcingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SupplierCollaborativeSourcingService::class);
    }

    public function test_supplier_co_development_and_undisclosed_cost_risk_penalty(): void
    {
        // 1. Open-book supplier co-development (302.1 & 302.2)
        $progOpen = $this->service->registerCoDevelopmentProgram(
            programCode: 'CODEV-STEEL-CHASSIS-01',
            supplierId: 'SUPPLIER_KRAKATAU_STEEL',
            briefTopic: 'Lightweight high-strength steel chassis',
            targetCostUsd: 1200.0,
            openBookDisclosed: true
        );
        $this->assertTrue((bool) $progOpen->cost_structure_disclosed);
        $this->assertEquals(0.00, (float) $progOpen->estimated_cost_risk_penalty_usd);

        // 2. Edge case 302.5: Supplier declining to disclose open book structure is not punished with exclusion, but incurs risk penalty
        $progClosed = $this->service->registerCoDevelopmentProgram(
            programCode: 'CODEV-CUSTOM-CHIP-02',
            supplierId: 'SUPPLIER_TSMC_PARTNER',
            briefTopic: 'Proprietary ECU microcontroller',
            targetCostUsd: 50000.0,
            openBookDisclosed: false
        );
        $this->assertFalse((bool) $progClosed->cost_structure_disclosed);
        $this->assertEquals(4000.0, (float) $progClosed->estimated_cost_risk_penalty_usd); // 8% risk penalty
    }

    public function test_reverse_auction_floor_price_and_tco_award(): void
    {
        // 1. Reverse auction bid below floor price is rejected (302.6 Margin Guardrail)
        try {
            $this->service->awardReverseAuction(
                auctionCode: 'AUC-LITHIUM-LOT-01',
                commodityLot: 'Battery Grade Lithium Hydroxide 500T',
                floorPriceUsd: 8500000.0,
                winningSupplierId: 'SUP_GANFENG',
                bidPriceUsd: 7900000.0, // Below floor!
                riskLogisticsQualityAdjustmentUsd: 100000.0,
                rationale: 'Lowest bid.'
            );
            $this->fail('Expected exception for bid below floor price');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('violates supplier viability floor price', $e->getMessage());
        }

        // 2. Valid TCO award documented (302.3, 302.4, 302.7)
        $award = $this->service->awardReverseAuction(
            auctionCode: 'AUC-LITHIUM-LOT-02',
            commodityLot: 'Battery Grade Lithium Hydroxide 500T',
            floorPriceUsd: 8500000.0,
            winningSupplierId: 'SUP_ALBEMARLE',
            bidPriceUsd: 8600000.0,
            riskLogisticsQualityAdjustmentUsd: 150000.0,
            rationale: 'Lowest Total Cost of Ownership considering regional logistics hub nearby.'
        );

        $this->assertEquals('SUP_ALBEMARLE', $award->awarded_supplier_id);
        $this->assertEquals(8750000.0, (float) $award->winning_tco_score);
        $this->assertTrue((bool) $award->sealed_bids_unopened_prior_to_event);
    }

    public function test_procurement_sourcing_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerCoDevelopmentProgram('CODEV-AUD', 'SUP', 'Brief', 1000.0, true);
        $this->service->awardReverseAuction('AUC-AUD', 'LOT', 100.0, 'SUP', 150.0, 10.0, 'Rationale');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: awarded auction without rationale
        DB::table('strategic_sourcing_reverse_auctions')->insert([
            'auction_code' => 'AUC-UNJUSTIFIED',
            'commodity_lot_name' => 'LOT-X',
            'floor_price_usd' => 100.0,
            'sealed_bids_unopened_prior_to_event' => true,
            'awarded_supplier_id' => 'SUP_ROGUE',
            'winning_tco_score' => 200.0,
            'award_justification_rationale' => null, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
