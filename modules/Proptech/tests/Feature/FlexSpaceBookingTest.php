<?php

namespace Modules\Proptech\tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Proptech\Application\Services\FlexSpaceService;
use Modules\Proptech\Domain\Models\BuildingSensor;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Proptech\Domain\Models\Flex\FlexSpace;
use Tests\TestCase;

class FlexSpaceBookingTest extends TestCase
{
    use RefreshDatabase;

    protected FlexSpaceService $flexService;

    protected BuildingZone $zone;

    protected FlexSpace $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flexService = app(FlexSpaceService::class);

        $this->zone = BuildingZone::create([
            'zone_code' => 'ZN-POD-01',
            'building_code' => 'BLD-COWORK-01',
            'name' => 'Coworking Lounge Zone',
            'floor_level' => 'L3',
            'area_sqm' => 120.0,
            'current_temp_c' => 24.0,
            'current_occupancy' => 5,
            'current_kwh_rate' => 1700.0,
        ]);

        $this->space = FlexSpace::create([
            'space_code' => 'MTR-POD-A',
            'name' => 'Executive Meeting Pod A',
            'space_type' => 'MEETING_ROOM',
            'zone_id' => $this->zone->id,
            'capacity_persons' => 6,
            'rate_per_hour_idr' => 150_000,
            'area_sqm' => 25.0,
            'status' => 'AVAILABLE',
        ]);

        LedgerAccount::create([
            'code' => 'prp:flex_escrow:IDR',
            'name' => 'Flex Space Escrow Deposit',
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'prp:flex_revenue_unearned:IDR',
            'name' => 'Flex Space Unearned Revenue',
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'prp:flex_revenue:IDR',
            'name' => 'Flex Space Revenue',
            'asset_code' => 'IDR',
            'kind' => 'revenue',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'prp:flex_penalty_rev:IDR',
            'name' => 'Flex Space No-Show Penalty Revenue',
            'asset_code' => 'IDR',
            'kind' => 'revenue',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);
    }

    public function test_78_2_booking_creation_deposit_hold_and_anti_overlap(): void
    {
        $start = Carbon::parse('2026-10-15 10:00:00');
        $end = Carbon::parse('2026-10-15 12:00:00'); // 2 hours = 300,000 IDR, deposit = 90,000 IDR

        $booking1 = $this->flexService->createBooking($this->space, 101, $start, $end);
        $this->assertEquals(300_000, $booking1->total_price_idr);
        $this->assertEquals(90_000, $booking1->deposit_amount_idr);
        $this->assertEquals('HELD', $booking1->status);

        // Check escrow deposit ledger entry
        $escrow = LedgerAccount::where('code', 'prp:flex_escrow:IDR')->first();
        $this->assertEquals('90000', (string) $escrow->cached_balance);

        // Concurrent overlapping booking fails
        $this->expectException(\RuntimeException::class);
        $this->flexService->createBooking(
            $this->space,
            102,
            Carbon::parse('2026-10-15 11:00:00'),
            Carbon::parse('2026-10-15 13:00:00')
        );
    }

    public function test_78_3_check_in_with_cryptographic_passport_and_session_utility_load(): void
    {
        $start = Carbon::parse('2026-10-16 14:00:00');
        $end = Carbon::parse('2026-10-16 16:00:00');

        $booking = $this->flexService->createBooking($this->space, 105, $start, $end);

        // Check in with valid cryptographic passport
        $passport = 'AGY-PASSPORT-PUBKEY-SIGNATURE-778899';
        $checkedIn = $this->flexService->checkInWithPassport($booking, $passport);

        $this->assertEquals('CHECKED_IN', $checkedIn->status);
        $this->assertEquals(hash('sha256', $passport), $checkedIn->checked_in_passport_hash);
        $this->assertNotNull($checkedIn->checked_in_at);

        // Revenue fully captured in ledger: -300,000 IDR to revenue account
        $rev = LedgerAccount::where('code', 'prp:flex_revenue:IDR')->first();
        $this->assertEquals('-300000', (string) $rev->cached_balance);

        // Utility reading added to zone
        $sensorLog = BuildingSensor::where('idempotency_key', "FLEX-SESSION-{$booking->booking_code}")->first();
        $this->assertNotNull($sensorLog);
        $this->assertEquals(15.0, (float) $sensorLog->reading_value);
    }

    public function test_78_2_no_show_forfeits_deposit_as_penalty_revenue(): void
    {
        $start = Carbon::parse('2026-10-17 09:00:00');
        $end = Carbon::parse('2026-10-17 11:00:00');

        $booking = $this->flexService->createBooking($this->space, 108, $start, $end);
        $this->assertEquals('HELD', $booking->status);

        $noShow = $this->flexService->handleNoShow($booking);
        $this->assertEquals('NO_SHOW', $noShow->status);

        // Penalty revenue earned = deposit amount (90,000 IDR)
        $penaltyRev = LedgerAccount::where('code', 'prp:flex_penalty_rev:IDR')->first();
        $this->assertEquals('-90000', (string) $penaltyRev->cached_balance);
    }
}
