<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use Modules\Hospital\Domain\Services\VaccineThermalStabilityCalculator;
use Tests\TestCase;

class VaccineThermalStabilityCalculatorTest extends TestCase
{
    private VaccineThermalStabilityCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new VaccineThermalStabilityCalculator;
    }

    public function test_normal_chiller_telemetry_produces_optimal_mkt(): void
    {
        // Rangkaian suhu stabil di antara 3.8°C dan 5.2°C
        $temperatures = [4.0, 4.2, 4.5, 4.8, 5.0, 4.3, 4.1, 3.9, 4.6, 5.1];

        $res = $this->calculator->calculateMeanKineticTemperature($temperatures);

        $this->assertCount(10, $temperatures);
        $this->assertSame(10, $res['sample_count']);
        $this->assertGreaterThan(3.5, $res['mkt_c']);
        $this->assertLessThan(6.0, $res['mkt_c']);
        $this->assertFalse($res['is_shelf_life_impacted']);
        $this->assertSame('RELEASE_APPROVED', $res['recommended_action']);
    }

    public function test_thermal_excursion_spike_increases_mkt_and_flags_impact(): void
    {
        // 8 jam normal (4°C), lalu lonjakan pemadaman listrik 2 jam (18°C)
        $temperatures = [4.0, 4.1, 4.0, 4.2, 17.5, 18.2, 16.0, 5.0, 4.5, 4.2];

        $res = $this->calculator->calculateMeanKineticTemperature($temperatures);

        // MKT harus lebih tinggi dari rata-rata aritmatika biasa karena bobot eksponensial suhu tinggi pada degradasi
        $this->assertGreaterThan($res['arithmetic_mean_c'], $res['mkt_c']);
        $this->assertTrue($res['is_shelf_life_impacted']);
        $this->assertSame('ACCELERATED_STABILITY_TESTING_REQUIRED', $res['recommended_action']);
    }
}
