<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\WaterEnergyManagementService;
use Tests\TestCase;

class WaterEnergyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected WaterEnergyManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WaterEnergyManagementService::class);
    }

    public function test_site_baseline_mv_savings_and_sustainment_flow(): void
    {
        // 446.1 Establish site water & energy baseline normalized to production units (10,000 units)
        $base = $this->service->establishSiteBaseline(
            siteCode: 'SITE-BEKASI-PLANT-01',
            siteName: 'Bekasi Powertrain Assembly Center',
            productionUnits: 10000.00,
            waterM3: 5000.00, // 0.5000 m3/unit
            energyKwh: 250000.00 // 25.0000 kWh/unit
        );

        $this->assertEquals(0.5000, (float) $base->normalized_water_intensity_m3_per_unit);
        $this->assertEquals(25.0000, (float) $base->normalized_energy_intensity_kwh_per_unit);

        // 446.2 Efficiency project: VFD installation on cooling pumps
        $proj = $this->service->registerEfficiencyProject(
            projectCode: 'PRJ-VFD-PUMPS-01',
            siteCode: 'SITE-BEKASI-PLANT-01',
            baselineConsumptionKwh: 80000.00
        );

        $this->assertEquals('implemented', $proj->status);

        // 446.2 & 446.4 Record verified M&V savings (measured post 55,000 kWh -> 25,000 kWh savings)
        $mv = $this->service->recordMvSavings('PRJ-VFD-PUMPS-01', 55000.00);
        $this->assertEquals(25000.00, (float) $mv->verified_kwh_savings);
        $this->assertTrue((bool) $mv->mv_measurement_verified);

        // 446.5 Sustainment audit after 6 months (measured 54,500 kWh <= baseline 80,000)
        $sustained = $this->service->completeSustainmentAudit('PRJ-VFD-PUMPS-01', 54500.00);
        $this->assertEquals('sustained', $sustained->status);
        $this->assertTrue((bool) $sustained->sustainment_audit_completed);

        // 446.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_negative_savings_and_zero_production_blocked_edge_cases(): void
    {
        // 446.6 Risk: Zero production units is blocked
        try {
            $this->service->establishSiteBaseline('SITE-BAD', 'Bad Plant', 0, 1000, 1000);
            $this->fail('Expected exception for zero production units');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Production units must be > 0 to normalize', $e->getMessage());
        }

        // 446.4 Post consumption exceeding baseline (negative savings) fails M&V
        $this->service->registerEfficiencyProject('PRJ-FAILED-RETROFIT', 'SITE-A', 50000.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('shows zero or negative savings vs baseline');

        $this->service->recordMvSavings('PRJ-FAILED-RETROFIT', 52000.00);
    }
}
