<?php

declare(strict_types=1);

namespace Modules\Mining\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Mining\Application\Services\MiningResourceCommandService;
use Modules\Mining\Domain\Models\MiningSite;
use Tests\TestCase;

class MiningResourceCommandTest extends TestCase
{
    use RefreshDatabase;

    protected MiningResourceCommandService $service;

    protected MiningSite $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MiningResourceCommandService::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-CMD-01',
            'name' => 'Sangatta Integrated Mining Operations Center',
            'commodity' => 'COAL',
            'location' => 'East Kalimantan',
        ]);
    }

    public function test_125_1_and_125_5_a_mine_to_market_margin_calculation(): void
    {
        // Realized revenue 120 Miliar IDR, operating cost 85 Miliar IDR -> net margin 35 Miliar IDR
        $snapshot = $this->service->recordDailySnapshot([
            'site_id' => $this->site->id,
            'snapshot_date' => '2026-10-08',
            'pit_production_tonnage' => 25000.0,
            'plant_processing_tonnage' => 24500.0,
            'terminal_loaded_tonnage' => 28000.0,
            'live_commodity_price_usd' => 142.50,
            'fleet_utilization_rate_pct' => 88.5,
            'realized_revenue_minor' => 120000000000,
            'total_operating_cost_minor' => 85000000000,
        ]);

        $this->assertEquals(35000000000, $snapshot->net_mine_to_market_margin_minor);
        $this->assertEquals($snapshot->realized_revenue_minor - $snapshot->total_operating_cost_minor, $snapshot->net_mine_to_market_margin_minor);
    }

    public function test_125_3_and_125_5_b_deterministic_life_of_mine_model(): void
    {
        // 150 Million tons proven reserve / 10 Million tons annual run rate = 15.0 years
        $lom = $this->service->calculateLifeOfMine($this->site->id, 150000000.0, 10000000.0, 250000000000);

        $this->assertEquals(15.0, $lom->life_of_mine_years);
        $this->assertEquals(150000000.0, $lom->proven_reserves_tonnage);
        $this->assertFalse($lom->capex_dao_approved);
    }

    public function test_125_4_and_125_5_c_risk_heatmap_alert_threshold(): void
    {
        // 12% drop when threshold is 15% -> no alert
        $noAlert = $this->service->evaluatePriceDropRisk($this->site->id, 15.0, 12.0);
        $this->assertNull($noAlert);

        // 18% drop when threshold is 15% -> HIGH alert
        $highAlert = $this->service->evaluatePriceDropRisk($this->site->id, 15.0, 18.0);
        $this->assertNotNull($highAlert);
        $this->assertEquals('HIGH', $highAlert->severity);
        $this->assertTrue($highAlert->c_suite_notified);

        // 28% drop -> CRITICAL alert
        $critAlert = $this->service->evaluatePriceDropRisk($this->site->id, 15.0, 28.0);
        $this->assertNotNull($critAlert);
        $this->assertEquals('CRITICAL', $critAlert->severity);
    }
}
