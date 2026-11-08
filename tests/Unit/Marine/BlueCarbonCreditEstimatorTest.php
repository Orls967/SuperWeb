<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use Modules\Marine\Domain\Algorithms\BlueCarbonCreditEstimator;
use Tests\TestCase;

class BlueCarbonCreditEstimatorTest extends TestCase
{
    protected BlueCarbonCreditEstimator $estimator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->estimator = new BlueCarbonCreditEstimator;
    }

    public function test_rhizophora_mangrove_blue_carbon_estimation_produces_verra_vm0033_metrics(): void
    {
        $result = $this->estimator->estimateBlueCarbon(
            'Rhizophora',
            50.0,  // 50 ha
            13.5,  // 13.5 cm DBH
            1800,  // 1800 pohon/ha
            100.0, // 100 cm depth
            0.85,  // bulk density
            3.0,   // %C
            0.15   // 15% buffer deduction
        );

        $this->assertGreaterThan(0.0, $result['aboveground_biomass_t_per_ha']);
        $this->assertGreaterThan(0.0, $result['belowground_biomass_t_per_ha']);
        $this->assertGreaterThan(0.0, $result['soil_carbon_t_per_ha']);
        $this->assertGreaterThan(0.0, $result['net_marketable_carbon_credits_tco2e']);
        $this->assertEquals('VERRA_VM0033_TIDAL_WETLAND_SILVOFISHERY', $result['methodology_standard']);
    }
}
