<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hotel\Application\Services\HotelTravelPassAndLoyaltyService;
use Modules\Hotel\Domain\Models\HotelProperty;
use RuntimeException;
use Tests\TestCase;

class HotelTravelPassAndLoyaltyTest extends TestCase
{
    use RefreshDatabase;

    protected HotelTravelPassAndLoyaltyService $service;

    protected HotelProperty $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HotelTravelPassAndLoyaltyService::class);

        $this->property = HotelProperty::create([
            'id' => (string) Str::uuid(),
            'property_code' => 'HTL-LOYALTY-01',
            'name' => 'The Imperial Palace Hotel',
            'property_type' => 'CITY_HOTEL',
            'city' => 'Jakarta',
            'total_rooms' => 200,
        ]);

        // Register hotel ledger accounts
        $accounts = [
            'htl:loyalty_program_expense:IDR' => 'expense',
            'htl:loyalty_points_liability:IDR' => 'liability',
            'htl:loyalty_redemption_settlement:IDR' => 'revenue',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Hotel {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_112_2_and_112_6_a_and_b_earn_and_redeem_points_matches_liability(): void
    {
        $pass = $this->service->registerTravelPass(101);
        $this->assertEquals(0, $pass->pts_balance);

        // 1. Earn 5,000 PTS from Hotel Stay (Liability = 5,000 * 100 IDR = 500,000 IDR)
        $this->service->earnPoints($pass, 'HOTEL', 5000, 'FOLIO-INV-001');
        $this->assertEquals(5000, $pass->refresh()->pts_balance);

        $liabilityAcct = LedgerAccount::where('code', 'htl:loyalty_points_liability:IDR')->first();
        $this->assertEquals('-500000', (string) $liabilityAcct->cached_balance);

        // 2. Redeem 2,000 PTS at Venue Beach Club (Liability discharged = 200,000 IDR)
        $this->service->redeemPoints($pass, 'VENUE', 2000, 'TICKET-FEST-99');
        $this->assertEquals(3000, $pass->refresh()->pts_balance);

        $liabilityAcct->refresh();
        $this->assertEquals('-300000', (string) $liabilityAcct->cached_balance);

        // 3. Attempting to redeem 4,000 PTS (more than 3,000) must fail
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient points');

        $this->service->redeemPoints($pass, 'RESTO', 4000, 'RESTO-ORDER-01');
    }

    public function test_112_4_and_112_6_d_dynamic_award_never_drops_below_floor(): void
    {
        // Low occupancy (10%): calculated pts would be 25,000 * 0.6 = 15,000, but floor is 18,000 PTS
        $pricing = $this->service->calculateDynamicAward(
            property: $this->property,
            roomType: 'DELUXE_ROOM',
            stayDate: '2026-11-01',
            occupancyPercent: 10.0,
            basePts: 25000,
            floorPts: 18000
        );

        $this->assertGreaterThanOrEqual(18000, $pricing->dynamic_award_pts);
        $this->assertEquals(18000, $pricing->dynamic_award_pts);
    }
}
