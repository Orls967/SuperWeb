<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\SupplyChainResilienceService;
use Tests\TestCase;

/**
 * Fase 153 — Supply Chain Resilience Tests
 *
 * Covers:
 *  (a) kritikal item single-source → alert / flag berkala
 *  (b) routing alternatif & geopolitical risk feed
 *  (c) buffer stock = kebijakan terhitung
 *  (d) simulator near-shoring tak mengubah data riil
 *  (e) tower:audit + proc:audit = 0 selisih
 */
class SupplyChainResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected SupplyChainResilienceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SupplyChainResilienceService::class);
    }

    /**
     * (a) Critical item single-source auto-flagging.
     */
    public function test_single_sourced_critical_items_are_flagged(): void
    {
        // 1. Critical item with 0 suppliers -> single sourced
        $item = $this->service->registerItem('RAW-NICKEL-001', 'High Purity Nickel Briquette', 'RAW_METALS', true);
        $this->assertTrue((bool) $item->is_single_sourced);

        // 2. Add 1st supplier -> Still single sourced (< 2)
        $this->service->addSupplier('RAW-NICKEL-001', 'SUP-VALE-ID', 'APAC', 100.0);
        $itemUpdated = \DB::table('scm_critical_items')->where('item_code', 'RAW-NICKEL-001')->first();
        $this->assertTrue((bool) $itemUpdated->is_single_sourced);

        // 3. Add 2nd supplier from different region -> Multi-sourced (flag clears)
        $this->service->addSupplier('RAW-NICKEL-001', 'SUP-GLENCORE-EU', 'EMEA', 50.0);
        $itemMulti = \DB::table('scm_critical_items')->where('item_code', 'RAW-NICKEL-001')->first();
        $this->assertFalse((bool) $itemMulti->is_single_sourced);
    }

    /**
     * (b) Geopolitical disruption events tracking.
     */
    public function test_geopolitical_disruption_tracking(): void
    {
        $event = $this->service->recordGeopoliticalDisruption(
            'EMEA',
            'PORT_BLOCKADE',
            'HIGH',
            'Strait of Malacca container delay 7 days'
        );

        $this->assertSame('EMEA', $event->region_code);
        $this->assertSame('PORT_BLOCKADE', $event->event_type);
        $this->assertTrue((bool) $event->is_active);
    }

    /**
     * (c) Strategic buffer stock risk adjusted calculation.
     */
    public function test_strategic_buffer_stock_calculation(): void
    {
        // Base safety: 1,000 units. Risk multiplier: 1.5x (high geopolitical tension). Carrying cost: Rp 25,000 / unit.
        $buffer = $this->service->calculateStrategicBuffer('RAW-NICKEL-001', 1000, 1.5, 25000.00);

        $this->assertSame(1500, (int) $buffer->risk_adjusted_buffer);
        $this->assertEquals(37500000.00, (float) $buffer->carrying_cost_idr);
        $this->assertTrue((bool) $buffer->treasury_approved);
    }

    /**
     * (d) Near-shoring simulator produces deterministic recommendations without state changes.
     */
    public function test_nearshoring_simulator(): void
    {
        // Offshore cost: $100. Domestic: $120. Freight savings: $35. Volume: 10,000 units.
        // Current: 10,000 * 100 = 1,000,000. Nearshore: (120 - 35) * 10,000 = 850,000. Net savings = 150,000.
        $sim = $this->service->simulateNearShoring(100.0, 120.0, 35.0, 10000);

        $this->assertSame('RECOMMEND_NEARSHORING', $sim['recommendation']);
        $this->assertEquals(150000.00, $sim['annual_net_savings']);
    }

    /**
     * (e) Audit status.
     */
    public function test_supply_chain_resilience_audit(): void
    {
        $this->service->registerItem('BATTERY-CELL-01', 'NMC Battery Cell', 'COMPONENTS', true);
        $this->service->addSupplier('BATTERY-CELL-01', 'SUP-CATL', 'APAC', 50.0);
        $this->service->addSupplier('BATTERY-CELL-01', 'SUP-LGES', 'APAC', 50.0);

        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['single_sourced_critical']);
    }
}
