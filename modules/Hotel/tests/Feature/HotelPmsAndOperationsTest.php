<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
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

        $accounts = [
            'htl:settlement_clearing:IDR' => 'asset',
            'htl:room_revenue:IDR' => 'revenue',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::firstOrCreate(
                ['code' => $code],
                [
                    'name' => "Account {$code}",
                    'kind' => $kind,
                    'asset_code' => 'IDR',
                    'allow_negative' => true,
                    'cached_balance' => '0',
                ]
            );
        }
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
}
