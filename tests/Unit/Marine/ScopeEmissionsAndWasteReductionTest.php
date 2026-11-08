<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use Database\Seeders\MarineAquacultureT1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marine\Application\Services\ScopeEmissionsAndWasteReductionService;
use Modules\Marine\Domain\Models\AquacultureBatch;
use Tests\TestCase;

class ScopeEmissionsAndWasteReductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarineAquacultureT1Seeder::class);
    }

    public function test_carbon_footprint_calculation_produces_scope1_2_3_breakdown(): void
    {
        $service = new ScopeEmissionsAndWasteReductionService;
        $batch = AquacultureBatch::first();

        $footprint = $service->calculateBatchCarbonFootprint(
            $batch->id,
            500.0,   // 500 liter solar
            1200.0,  // 1200 kWh PLN
            4000.0,  // 4000 kg pakan
            3300.0,  // 3300 kg udang
            1800.0   // 1800 kg lumpur sediment
        );

        $this->assertGreaterThan(0.0, $footprint['scope1_diesel_emissions_kg_co2']);
        $this->assertGreaterThan(0.0, $footprint['scope2_electricity_emissions_kg_co2']);
        $this->assertGreaterThan(0.0, $footprint['scope3_feed_embedded_emissions_kg_co2']);
        $this->assertGreaterThan(0.0, $footprint['total_carbon_footprint_tonnes_co2e']);
        $this->assertGreaterThan(0.0, $footprint['carbon_intensity_kg_co2_per_kg_shrimp']);
        $this->assertEquals(1.8, $footprint['waste_sludge_diverted_tonnes']);
    }
}
