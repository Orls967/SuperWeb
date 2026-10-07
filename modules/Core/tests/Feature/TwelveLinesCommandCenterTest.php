<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Modules\Core\Application\Services\TwelveLinesCommandCenterService;
use Tests\TestCase;

class TwelveLinesCommandCenterTest extends TestCase
{
    protected TwelveLinesCommandCenterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TwelveLinesCommandCenterService::class);
    }

    public function test_group_wide_12_lines_carbon_balance_aggregation(): void
    {
        $emissions = [
            'MINING' => ['scope_1_kg' => 50000.0, 'scope_2_kg' => 15000.0, 'scope_3_kg' => 5000.0],
            'LOGISTICS' => ['scope_1_kg' => 30000.0, 'scope_2_kg' => 2000.0, 'scope_3_kg' => 8000.0],
            'HOTEL' => ['scope_1_kg' => 2000.0, 'scope_2_kg' => 18000.0, 'scope_3_kg' => 3000.0],
            'HOSPITAL' => ['scope_1_kg' => 1500.0, 'scope_2_kg' => 25000.0, 'scope_3_kg' => 4500.0],
        ];

        $res = $this->service->aggregateTwelveLinesCarbonEmissions($emissions);

        $this->assertEquals(83500.0, $res['total_scope_1_kg']);
        $this->assertEquals(60000.0, $res['total_scope_2_kg']);
        $this->assertEquals(20500.0, $res['total_scope_3_kg']);
        $this->assertEquals(164000.0, $res['total_emissions_kg']);
        $this->assertEquals(164.0, $res['total_ton_co2e']);
        $this->assertEquals(4, $res['reporting_lines_count']);
    }

    public function test_pillar_health_score_composite(): void
    {
        $res = $this->service->computePillarHealthScore(
            finScore: 90.0,
            opsScore: 85.0,
            esgScore: 80.0,
            complianceScore: 95.0
        );

        // (90 * 0.35 = 31.5) + (85 * 0.25 = 21.25) + (80 * 0.20 = 16) + (95 * 0.20 = 19) = 87.75 -> 87.8
        $this->assertEquals(87.8, $res['composite_score']);
        $this->assertEquals('OPTIMAL', $res['status']);
    }
}
