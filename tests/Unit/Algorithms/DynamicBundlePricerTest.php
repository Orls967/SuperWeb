<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Venue\Domain\Algorithms\Bundling\DynamicBundleInput;
use Modules\Venue\Domain\Algorithms\Bundling\DynamicBundlePricer;
use Tests\TestCase;

final class DynamicBundlePricerTest extends TestCase
{
    private DynamicBundlePricer $pricer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricer = new DynamicBundlePricer;
    }

    public function test_dynamic_bundle_applies_surge_and_lead_time_discount(): void
    {
        $components = [
            ['name' => 'VIP Pass', 'floor_cost' => 500000, 'base_price' => 1000000],
            ['name' => 'Suite Room', 'floor_cost' => 700000, 'base_price' => 1400000],
        ];

        // Total floor = 1.2M, Total base = 2.4M
        // avgOccupancy = 90% (>70%), surge = (90 - 70) * 70 = 1400 bps (14%)
        // lead time 35 days -> discount = 1500 bps (15%)
        $input = new DynamicBundleInput(
            components: $components,
            venueOccupancyPercentage: 90,
            hotelOccupancyPercentage: 90,
            leadTimeDays: 35
        );

        $result = $this->pricer->calculatePrice($input);

        $this->assertSame(1200000, $result->totalFloorCostIdr);
        $this->assertSame(2400000, $result->totalBasePriceIdr);
        $this->assertSame(1400, $result->surgeBps);
        $this->assertSame(1500, $result->leadTimeDiscountBps);
        // raw = 2.4M + 336k - 360k = 2.376.000 >= 1.2M
        $this->assertSame(2376000, $result->finalBundlePriceIdr);
        $this->assertTrue($result->certificate?->isValid());
    }

    public function test_enforces_floor_margin_guardrail_when_discount_drives_price_below_floor(): void
    {
        $components = [
            ['name' => 'Discounted Pass', 'floor_cost' => 900000, 'base_price' => 950000],
        ];

        // Zero surge (occupancy 50%), max discount 15% (lead time 40 days)
        // Raw = 950k - 15% (142.500) = 807.500 (< floor cost 900.000)
        // Floor Margin Guardrail enforces finalPrice = max(raw, floorCost) = 900.000
        $input = new DynamicBundleInput(
            components: $components,
            venueOccupancyPercentage: 50,
            hotelOccupancyPercentage: 50,
            leadTimeDays: 40
        );

        $result = $this->pricer->calculatePrice($input);

        $this->assertSame(900000, $result->finalBundlePriceIdr);
        $this->assertGreaterThanOrEqual($result->totalFloorCostIdr, $result->finalBundlePriceIdr);
        $this->assertTrue($result->certificate?->isValid());
    }
}
