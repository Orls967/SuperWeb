<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Services\HotelOperationsService;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRoom;
use Tests\TestCase;

class HotelPmsAndOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected HotelOperationsService $hotelService;

    protected HotelProperty $property;

    protected HotelRoom $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hotelService = app(HotelOperationsService::class);

        $this->property = HotelProperty::create([
            'id' => (string) Str::uuid(),
            'property_code' => 'HTL-BALI-RESORT-01',
            'name' => 'The Mulia Nusa Dua Luxury Resort',
            'property_type' => 'RESORT',
            'city' => 'Badung',
            'total_rooms' => 10,
        ]);

        $this->room = HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_number' => 'VILLA-101',
            'room_type' => 'VILLA',
            'base_rate_idr' => 3000000, // 3M IDR floor
            'status' => 'available',
        ]);

        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_reservation_creation_with_floor_rate_and_contract_rate(): void
    {
        $guestId = (string) Str::uuid();

        // 1. Requested below floor rate (2M < 3M) -> floor enforced (3M)
        $res = $this->hotelService->createReservation(
            propertyId: $this->property->id,
            roomType: 'VILLA',
            guestUserId: $guestId,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03', // 2 nights
            requestedRateIdr: 2000000,
            contractRateIdr: null
        );

        $this->assertEquals(3000000, $res->daily_rate_idr);
        $this->assertEquals(6000000, $res->total_room_charge_idr);
        $this->assertEquals('confirmed', $res->status);

        // 2. Anti-oversell: room now reserved, another booking for same VILLA should fail
        $this->expectException(\RuntimeException::class);
        $this->hotelService->createReservation(
            propertyId: $this->property->id,
            roomType: 'VILLA',
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            requestedRateIdr: 4000000
        );
    }

    public function test_check_in_smart_lock_and_energy_twin_setback(): void
    {
        $guestId = (string) Str::uuid();
        $res = $this->hotelService->createReservation(
            propertyId: $this->property->id,
            roomType: 'VILLA',
            guestUserId: $guestId,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            requestedRateIdr: 3500000
        );

        $resCheckedIn = $this->hotelService->checkIn($res->id);
        $this->assertEquals('checked_in', $resCheckedIn->status);

        $room = $this->room->fresh();
        $this->assertEquals('occupied', $room->status);
        $this->assertNotNull($room->smart_lock_token);
        $this->assertFalse($room->energy_setback_active);

        // Access verification
        $valid = $this->hotelService->verifySmartLockAccess($room->id, $room->smart_lock_token);
        $this->assertTrue($valid);

        $invalid = $this->hotelService->verifySmartLockAccess($room->id, 'INVALID-KEY');
        $this->assertFalse($invalid);

        // Energy setback when guest leaves for sightseeing
        $setbackRoom = $this->hotelService->setRoomEnergySetback($room->id, true);
        $this->assertTrue($setbackRoom->energy_setback_active);
    }

    public function test_checkout_and_folio_settlement_posting_to_ledger(): void
    {
        $guestId = (string) Str::uuid();
        $res = $this->hotelService->createReservation(
            propertyId: $this->property->id,
            roomType: 'VILLA',
            guestUserId: $guestId,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-02', // 1 night
            requestedRateIdr: 3000000
        );

        $this->hotelService->checkIn($res->id);

        $folio = $this->hotelService->checkOutAndSettleFolio($res->id, 3000000);

        $this->assertEquals(0, $folio->outstanding_balance);
        $this->assertEquals('settled', $folio->status);

        $room = $this->room->fresh();
        $this->assertEquals('available', $room->status);
        $this->assertNull($room->smart_lock_token);
        $this->assertTrue($room->energy_setback_active);
    }

    public function test_a_oversell_within_percent_limit_and_reconfirmation_flow(): void
    {
        // Create a property with 100 rooms of type SUITE
        $property = HotelProperty::create([
            'id' => (string) Str::uuid(),
            'property_code' => 'HTL-JAKARTA-01',
            'name' => 'Grand Hyatt Jakarta',
            'property_type' => 'CITY_HOTEL',
            'city' => 'Jakarta Pusat',
            'total_rooms' => 100,
        ]);

        for ($i = 1; $i <= 100; $i++) {
            HotelRoom::create([
                'id' => (string) Str::uuid(),
                'property_id' => $property->id,
                'room_number' => "SUITE-{$i}",
                'room_type' => 'SUITE',
                'base_rate_idr' => 2000000,
                'status' => 'available',
            ]);
        }

        // Fill all 100 rooms
        for ($i = 1; $i <= 100; $i++) {
            $this->hotelService->createReservation(
                propertyId: $property->id,
                roomType: 'SUITE',
                guestUserId: (string) Str::uuid(),
                checkInDate: '2026-12-01',
                checkOutDate: '2026-12-02',
                requestedRateIdr: 2000000
            );
        }

        // 101, 102, 103 should be accepted with status confirmed_overbooked (3% overbooking limit)
        $overbooked1 = $this->hotelService->createReservation(
            propertyId: $property->id,
            roomType: 'SUITE',
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-12-01',
            checkOutDate: '2026-12-02',
            requestedRateIdr: 2000000
        );
        $this->assertEquals('confirmed_overbooked', $overbooked1->status);

        // Reconfirmation flow
        $reconfirmed = $this->hotelService->reconfirmReservation($overbooked1->id);
        $this->assertEquals('confirmed_reconfirmed', $reconfirmed->status);

        $overbooked2 = $this->hotelService->createReservation(
            propertyId: $property->id,
            roomType: 'SUITE',
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-12-01',
            checkOutDate: '2026-12-02',
            requestedRateIdr: 2000000
        );
        $this->assertEquals('confirmed_overbooked', $overbooked2->status);

        $overbooked3 = $this->hotelService->createReservation(
            propertyId: $property->id,
            roomType: 'SUITE',
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-12-01',
            checkOutDate: '2026-12-02',
            requestedRateIdr: 2000000
        );
        $this->assertEquals('confirmed_overbooked', $overbooked3->status);

        // 104th reservation exceeds 3% overbooking limit and must be rejected
        $this->expectException(\RuntimeException::class);
        $this->hotelService->createReservation(
            propertyId: $property->id,
            roomType: 'SUITE',
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-12-01',
            checkOutDate: '2026-12-02',
            requestedRateIdr: 2000000
        );
    }

    public function test_b_smart_lock_duplicate_and_expired_token_rejected(): void
    {
        $guestId = (string) Str::uuid();
        $res = $this->hotelService->createReservation(
            propertyId: $this->property->id,
            roomType: 'VILLA',
            guestUserId: $guestId,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            requestedRateIdr: 3000000
        );

        $this->hotelService->checkIn($res->id);
        $room = $this->room->fresh();

        // Valid token accepted
        $this->assertTrue($this->hotelService->verifySmartLockAccess($room->id, $room->smart_lock_token));

        // Invalid token rejected
        $this->assertFalse($this->hotelService->verifySmartLockAccess($room->id, 'WRONG-TOKEN-999'));

        // Expired token rejected
        $room->update(['smart_lock_expires_at' => now()->subDay()]);
        $this->assertFalse($this->hotelService->verifySmartLockAccess($room->id, $room->smart_lock_token));
    }

    public function test_c_dynamic_rate_respects_floor_and_contract_rate_wins(): void
    {
        // 1. Below floor rate (1,500,000 < 3,000,000) -> floor rate (3,000,000) enforced
        $res1 = $this->hotelService->createReservation(
            propertyId: $this->property->id,
            roomType: 'VILLA',
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-11-10',
            checkOutDate: '2026-11-12',
            requestedRateIdr: 1500000,
            contractRateIdr: null
        );
        $this->assertEquals(3000000, $res1->daily_rate_idr);

        // 2. Corporate contract rate (2,500,000) wins immutably over floor and requested rate
        $res1->update(['status' => 'cancelled']);
        HotelRoom::where('id', $this->room->id)->update(['status' => 'available']);
        $res2 = $this->hotelService->createReservation(
            propertyId: $this->property->id,
            roomType: 'VILLA',
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-11-10',
            checkOutDate: '2026-11-12',
            requestedRateIdr: 4500000,
            contractRateIdr: 2500000
        );
        $this->assertEquals(2500000, $res2->daily_rate_idr);
    }

    public function test_d_vacant_room_triggers_energy_setback(): void
    {
        $guestId = (string) Str::uuid();
        $res = $this->hotelService->createReservation(
            propertyId: $this->property->id,
            roomType: 'VILLA',
            guestUserId: $guestId,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-02',
            requestedRateIdr: 3000000
        );

        $this->hotelService->checkIn($res->id);
        $this->assertFalse($this->room->fresh()->energy_setback_active);

        // Checkout sets vacant room setback active
        $this->hotelService->checkOutAndSettleFolio($res->id, 3000000);
        $this->assertTrue($this->room->fresh()->energy_setback_active);
    }

    public function test_e_hotel_audit_reconciles_room_night_revenue_with_ledger(): void
    {
        $exitHotelAudit = Artisan::call('hotel:audit');
        $this->assertSame(0, $exitHotelAudit);
        $this->assertStringContainsString('Hotel audit PASSED with 0 discrepancy', Artisan::output());

        $exitHtlAudit = Artisan::call('htl:audit');
        $this->assertSame(0, $exitHtlAudit);
        $this->assertStringContainsString('Hotel audit PASSED with 0 discrepancy', Artisan::output());
    }
}
