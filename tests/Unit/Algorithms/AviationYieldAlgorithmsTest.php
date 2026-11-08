<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Aviation\Domain\Algorithms\CodeshareMileageAccrualEngine;
use Modules\Aviation\Domain\Algorithms\DisruptedPassengerReaccommodator;
use Modules\Aviation\Domain\Algorithms\FareBucketYieldOptimizer;
use Modules\Aviation\Domain\Algorithms\PassengerCareObligationCalculator;
use Tests\TestCase;

class AviationYieldAlgorithmsTest extends TestCase
{
    /**
     * Uji Algoritma A6: EMSR-b Yield Optimizer deterministik & presisi Littlewood.
     */
    public function test_a6_emsr_b_yield_optimizer(): void
    {
        $optimizer = new FareBucketYieldOptimizer;

        $classes = [
            ['code' => 'Y', 'fare' => 2500000, 'mean_demand' => 25, 'std_dev' => 5],
            ['code' => 'B', 'fare' => 1800000, 'mean_demand' => 35, 'std_dev' => 8],
            ['code' => 'M', 'fare' => 1200000, 'mean_demand' => 50, 'std_dev' => 10],
            ['code' => 'V', 'fare' => 750000, 'mean_demand' => 60, 'std_dev' => 12],
        ];

        $capacity = 160;
        $res = $optimizer->calculateProtectionAndBookingLimits($capacity, $classes);

        $this->assertEquals(160, $res['total_capacity']);
        $this->assertCount(4, $res['buckets']);
        $this->assertNotEmpty($res['certificate_hash']);

        // Uji properti monotonic nesting
        $yLimit = $res['buckets'][0]['booking_limit'];
        $bLimit = $res['buckets'][1]['booking_limit'];
        $mLimit = $res['buckets'][2]['booking_limit'];
        $vLimit = $res['buckets'][3]['booking_limit'];

        $this->assertEquals(160, $yLimit);
        $this->assertLessThanOrEqual($yLimit, $bLimit);
        $this->assertLessThanOrEqual($bLimit, $mLimit);
        $this->assertLessThanOrEqual($mLimit, $vLimit);

        // Uji inverse standard normal CDF
        $this->assertEqualsWithDelta(0.0, $optimizer->inverseStandardNormalCdf(0.5), 0.001);
        $this->assertGreaterThan(0.0, $optimizer->inverseStandardNormalCdf(0.8));
        $this->assertLessThan(0.0, $optimizer->inverseStandardNormalCdf(0.2));
    }

    /**
     * Uji Algoritma A7: Disrupted Passenger Reaccommodator.
     */
    public function test_a7_disrupted_passenger_reaccommodator(): void
    {
        $reaccommodator = new DisruptedPassengerReaccommodator;

        $passengers = [
            ['id' => 101, 'name' => 'General Pax', 'tier' => 'classic', 'cabin' => 'economy', 'has_connection' => false, 'special' => null, 'booking_time' => 100],
            ['id' => 102, 'name' => 'Gold Member Pax', 'tier' => 'gold', 'cabin' => 'economy', 'has_connection' => false, 'special' => null, 'booking_time' => 105],
            ['id' => 103, 'name' => 'Wheelchair Pax', 'tier' => 'classic', 'cabin' => 'economy', 'has_connection' => false, 'special' => 'WCHR', 'booking_time' => 110],
            ['id' => 104, 'name' => 'Platinum Connected', 'tier' => 'platinum', 'cabin' => 'business', 'has_connection' => true, 'special' => null, 'booking_time' => 115],
            ['id' => 105, 'name' => 'Unaccompanied Minor', 'tier' => 'classic', 'cabin' => 'economy', 'has_connection' => false, 'special' => 'UMNR', 'booking_time' => 120],
        ];

        $sorted = $reaccommodator->prioritizePassengers($passengers);

        // UMNR (skor 20.000 + ...) dan Platinum Business Connection (10.000 + 3.000 + 8.000 = 21.000)
        $this->assertEquals(104, $sorted[0]['id']); // Platinum Biz Conn = 21.500
        $this->assertEquals(105, $sorted[1]['id']); // UMNR = 21.000
        $this->assertEquals(103, $sorted[2]['id']); // WCHR = 16.000
        $this->assertEquals(102, $sorted[3]['id']); // Gold = 6.000
        $this->assertEquals(101, $sorted[4]['id']); // General = 1.000

        // Uji perhitungan alokasi hotel
        $familyPax = [
            ['id' => 1, 'is_family' => true, 'family_group_id' => 'FAM-A'],
            ['id' => 2, 'is_family' => true, 'family_group_id' => 'FAM-A'],
            ['id' => 3, 'is_family' => true, 'family_group_id' => 'FAM-A'],
            ['id' => 4, 'is_family' => false, 'family_group_id' => null],
        ];
        $rooms = $reaccommodator->calculateHotelRoomRequirements($familyPax);
        $this->assertEquals(4, $rooms['total_passengers']);
        $this->assertEquals(1, $rooms['single_rooms']);
        $this->assertEquals(2, $rooms['twin_rooms']); // 3 orang = 2 kamar
        $this->assertEquals(3, $rooms['total_rooms']);
    }

    /**
     * Uji Algoritma A8: Codeshare Proration & Anti-Double Dipping Engine.
     */
    public function test_a8_codeshare_proration_and_miles(): void
    {
        $engine = new CodeshareMileageAccrualEngine;

        $grossFare = 300000000; // Rp3.000.000
        $distance = 700; // 700 miles

        $res = $engine->calculateRevenueShareAndMiles(
            grossFareMinorUnit: $grossFare,
            tpmDistance: $distance,
            cabinClass: 'business',
            passengerTier: 'platinum',
            operatingAirlineBps: 8000,
            marketingAirlineBps: 2000
        );

        $this->assertEquals(240000000, $res['operating_share_minor_unit']); // 80%
        $this->assertEquals(60000000, $res['marketing_share_minor_unit']);   // 20%
        $this->assertEquals(1.5, $res['cabin_multiplier']);
        $this->assertEquals(1.0, $res['tier_bonus_multiplier']);
        // Base miles: 700 * 1.5 = 1050; Bonus: 1050 * 1.0 = 1050; Total = 2100
        $this->assertEquals(2100, $res['miles_accrued']);

        // Nonce composite hash deterministik
        $h1 = $engine->generateClaimNonceHash('1269988776655', 1, '2026-11-08');
        $h2 = $engine->generateClaimNonceHash('1269988776655', 1, '2026-11-08');
        $this->assertSame($h1, $h2);
        $this->assertEquals(64, strlen($h1));
    }

    /**
     * Uji Algoritma A9: PM 89/2015 Passenger Care Obligation Calculator.
     */
    public function test_a9_passenger_care_calculator(): void
    {
        $calculator = new PassengerCareObligationCalculator;

        // Kategori 2: Delay 90 menit
        $cat2 = $calculator->evaluateObligation(90, 'AOG_TECHNICAL');
        $this->assertEquals('CAT2', $cat2['category']);
        $this->assertEquals('snack', $cat2['voucher_type']);
        $this->assertEquals(5000000, $cat2['voucher_nominal_minor_unit']);
        $this->assertFalse($cat2['cash_compensation_required']);

        // Kategori 4: Delay 200 menit
        $cat4 = $calculator->evaluateObligation(200, 'AOG_TECHNICAL');
        $this->assertEquals('CAT4', $cat4['category']);
        $this->assertEquals('meal', $cat4['voucher_type']);
        $this->assertTrue($cat4['reroute_option_required']);

        // Kategori 5: Delay 300 menit (Teknis)
        $cat5 = $calculator->evaluateObligation(300, 'AOG_TECHNICAL');
        $this->assertEquals('CAT5', $cat5['category']);
        $this->assertTrue($cat5['cash_compensation_required']);
        $this->assertEquals(30000000, $cat5['cash_compensation_amount_minor_unit']); // Rp300.000,-

        // Pembatalan (Kategori 6)
        $cat6 = $calculator->evaluateObligation(0, 'CANCELLATION');
        $this->assertEquals('CAT6_CANCELLED', $cat6['category']);
        $this->assertTrue($cat6['hotel_required']);
        $this->assertTrue($cat6['refund_option_required']);
    }
}
