<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use Modules\Marine\Domain\Algorithms\FeedConversionRatioOptimizer;
use Tests\TestCase;

class FeedConversionRatioTest extends TestCase
{
    protected FeedConversionRatioOptimizer $optimizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->optimizer = new FeedConversionRatioOptimizer;
    }

    public function test_fcr_calculation_produces_accurate_economic_and_biological_ratios(): void
    {
        // Pakan tebar: 4000 kg, Biomassa awal: 100 kg, Biomassa panen: 3300 kg
        // Gain = 3200 kg -> FCR = 4000 / 3200 = 1.250
        $result = $this->optimizer->calculateFcr(4000.0, 100.0, 3300.0, 100.0);

        $this->assertEquals(1.25, $result['economic_fcr']);
        $this->assertEquals('EXCELLENT', $result['efficiency_rating']);
        $this->assertEquals(3200.0, $result['biomass_gain_kg']);
    }

    public function test_daily_ration_adjustment_throttles_down_on_low_dissolved_oxygen(): void
    {
        // DO turun di bawah batas kritis (2.2 mg/L)
        $adj = $this->optimizer->calculateDailyRationAdjustment(100.0, 2.2, 29.0, 7.8, 1.0);

        $this->assertEquals(50.0, $adj['adjusted_daily_feed_kg']);
        $this->assertContains('DO_LOW_CUT_50_PERCENT', $adj['limitation_factors']);
    }

    public function test_daily_ration_adjustment_halts_completely_on_lethal_anoxia(): void
    {
        // DO < 1.5 mg/L darurat kematian masal
        $adj = $this->optimizer->calculateDailyRationAdjustment(100.0, 1.2, 29.0, 7.8, 1.0);

        $this->assertEquals(0.0, $adj['adjusted_daily_feed_kg']);
        $this->assertContains('DO_CRITICAL_STOP_FEEDING', $adj['limitation_factors']);
        $this->assertEquals('STOP_FEEDING_IMMEDIATELY_ACTIVATE_AERATORS', $adj['feeding_recommendation']);
    }
}
