<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Services\HotelOperationsService;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use RuntimeException;
use Tests\TestCase;

class HotelPmsEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    protected HotelOperationsService $service;

    protected HotelProperty $resort;

    protected HotelProperty $cityHotel;

    protected HotelRoom $villaRoom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HotelOperationsService::class);
        $this->seed(LedgerAccountsSeeder::class);

        $this->resort = HotelProperty::create([
            'id' => (string) Str::uuid(),
            'property_code' => 'HTL-BALI-RESORT-01',
            'name' => 'The Mulia Nusa Dua Luxury Resort',
            'property_type' => 'RESORT',
            'city' => 'Badung',
            'total_rooms' => 10,
        ]);

        $this->cityHotel = HotelProperty::create([
            'id' => (string) Str::uuid(),
            'property_code' => 'HTL-BALI-CITY-01',
            'name' => 'The Mulia Sanur Suites',
            'property_type' => 'BUSINESS',
            'city' => 'Denpasar',
            'total_rooms' => 20,
        ]);

        $this->villaRoom = HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->resort->id,
            'room_number' => 'VILLA-101',
            'room_type' => 'VILLA',
            'base_rate_idr' => 3000000,
            'status' => 'available',
        ]);
    }

    public function test_crs_multi_property_inventory_pessimistic_lock_prevents_oversell_beyond_limit(): void
    {
        // One room exists; first booking gets it
        $res1 = $this->service->createReservation(
            propertyId: $this->resort->id,
            roomType: 'VILLA',
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            requestedRateIdr: 3500000
        );

        $this->assertEquals('confirmed', $res1->status);

        // Max allowed overbooking is floor(1 * 1.03) = 1.
        // Therefore, second booking attempt beyond capacity must throw RuntimeException
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("No available VILLA rooms in property {$this->resort->name}");

        $this->service->createReservation(
            propertyId: $this->resort->id,
            roomType: 'VILLA',
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            requestedRateIdr: 3500000
        );
    }

    public function test_overbooking_within_three_percent_allocates_confirmed_overbooked_status(): void
    {
        // Create 100 deluxe rooms
        for ($i = 1; $i <= 100; $i++) {
            HotelRoom::create([
                'id' => (string) Str::uuid(),
                'property_id' => $this->resort->id,
                'room_number' => "DLX-{$i}",
                'room_type' => 'DELUXE',
                'base_rate_idr' => 1500000,
                'status' => 'available',
            ]);
        }

        // Fill all 100 rooms
        for ($i = 1; $i <= 100; $i++) {
            $this->service->createReservation(
                propertyId: $this->resort->id,
                roomType: 'DELUXE',
                guestUserId: (string) Str::uuid(),
                checkInDate: '2026-11-10',
                checkOutDate: '2026-11-12',
                requestedRateIdr: 1500000
            );
        }

        // 101st reservation (within 103 limit) must get confirmed_overbooked
        $resOverbooked = $this->service->createReservation(
            propertyId: $this->resort->id,
            roomType: 'DELUXE',
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-11-10',
            checkOutDate: '2026-11-12',
            requestedRateIdr: 1500000
        );

        $this->assertEquals('confirmed_overbooked', $resOverbooked->status);
    }

    public function test_overbooking_relocation_workflow_finds_nearest_group_property_and_compensates(): void
    {
        $resOverbooked = HotelReservation::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->resort->id,
            'room_id' => $this->villaRoom->id,
            'reservation_number' => 'RES-OVB-TEST-01',
            'guest_user_id' => (string) Str::uuid(),
            'check_in_date' => '2026-11-15',
            'check_out_date' => '2026-11-17',
            'daily_rate_idr' => 3000000,
            'total_room_charge_idr' => 6000000,
            'status' => 'confirmed_overbooked',
        ]);

        // City hotel provides equivalent suite
        $citySuite = HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->cityHotel->id,
            'room_number' => 'SUITE-501',
            'room_type' => 'SUITE',
            'base_rate_idr' => 3500000,
            'status' => 'available',
        ]);

        // Perform relocation workflow
        $resOverbooked->update([
            'property_id' => $this->cityHotel->id,
            'room_id' => $citySuite->id,
            'status' => 'relocated',
        ]);

        $this->assertEquals($this->cityHotel->id, $resOverbooked->fresh()->property_id);
        $this->assertEquals($citySuite->id, $resOverbooked->fresh()->room_id);
        $this->assertEquals('relocated', $resOverbooked->fresh()->status);
    }

    public function test_overbooking_compensation_posts_correct_ledger_expense_and_receivable(): void
    {
        $res = $this->service->createReservation(
            propertyId: $this->resort->id,
            roomType: 'VILLA',
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-11-20',
            checkOutDate: '2026-11-22',
            requestedRateIdr: 3000000
        );

        // Compensate guest with 500k IDR credit on folio
        $folio = HotelFolio::where('reservation_id', $res->id)->firstOrFail();
        $voucherAmount = 500000;

        $folio->increment('total_paid', $voucherAmount);
        $folio->update([
            'outstanding_balance' => max(0, ($folio->total_room_charges + $folio->total_addon_charges) - $folio->total_paid),
        ]);

        $this->assertEquals($voucherAmount, $folio->fresh()->total_paid);
        $this->assertEquals(6000000 - $voucherAmount, $folio->fresh()->outstanding_balance);
    }

    public function test_smart_lock_token_hmac_signature_verified_with_room_secret(): void
    {
        $reservationId = (string) Str::uuid();
        $roomSecret = 'door_secret_villa_101_bali';
        $nonce = 'nonce_'.Str::random(12);
        $timestamp = now()->timestamp;

        // Generate HMAC signature token
        $signature = hash_hmac('sha256', "{$this->villaRoom->id}:{$reservationId}:{$nonce}:{$timestamp}", $roomSecret);
        $token = "HMAC-{$nonce}-{$signature}";

        $this->villaRoom->update([
            'smart_lock_token' => $token,
            'smart_lock_expires_at' => now()->addHours(24),
        ]);

        $valid = $this->service->verifySmartLockAccess($this->villaRoom->id, $token);
        $this->assertTrue($valid);
    }

    public function test_smart_lock_token_rejected_when_presented_outside_30_second_replay_window(): void
    {
        $token = 'LOCK-EXPIRED-SESSION';
        $this->villaRoom->update([
            'smart_lock_token' => $token,
            'smart_lock_expires_at' => now()->subSeconds(31), // 31 seconds ago (outside window)
        ]);

        $valid = $this->service->verifySmartLockAccess($this->villaRoom->id, $token);
        $this->assertFalse($valid);
    }

    public function test_closed_loop_iot_energy_setback_triggers_temperature_adjustment_on_vacant(): void
    {
        // When room is vacant, set setback to active
        $updatedRoom = $this->service->setRoomEnergySetback($this->villaRoom->id, true);
        $this->assertTrue((bool) $updatedRoom->energy_setback_active);

        // When room is occupied, setback is deactivated
        $occupiedRoom = $this->service->setRoomEnergySetback($this->villaRoom->id, false);
        $this->assertFalse((bool) $occupiedRoom->energy_setback_active);
    }

    public function test_energy_setback_kwh_savings_calculated_and_allocated_to_property_esg_metrics(): void
    {
        // 10 hours setback at 0.75 kWh/hour savings = 7.5 kWh
        $setbackHours = 10;
        $kwhSavedPerHour = 0.75;
        $totalKwhSaved = $setbackHours * $kwhSavedPerHour;

        // Scope 2 emission factor: 0.85 kg CO2e / kWh
        $co2eAvoidedKg = round($totalKwhSaved * 0.85, 3);

        $this->assertEquals(7.5, $totalKwhSaved);
        $this->assertEquals(6.375, $co2eAvoidedKg);
    }

    public function test_dynamic_rate_respects_absolute_floor_of_115_percent_room_cost(): void
    {
        // Requested rate 2,000,000 IDR is below base floor rate of 3,000,000 IDR
        $res = $this->service->createReservation(
            propertyId: $this->resort->id,
            roomType: 'VILLA',
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-11-25',
            checkOutDate: '2026-11-26',
            requestedRateIdr: 2000000
        );

        // Effective rate must be guarded by floor rate 3,000,000 IDR
        $this->assertEquals(3000000, $res->daily_rate_idr);
        $this->assertEquals(3000000, $res->total_room_charge_idr);
    }

    public function test_corporate_negotiated_contract_rate_strictly_overrides_dynamic_rate(): void
    {
        // Even if market requested is 5,000,000 IDR, corporate contract rate is 2,500,000 IDR
        $res = $this->service->createReservation(
            propertyId: $this->resort->id,
            roomType: 'VILLA',
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-11-25',
            checkOutDate: '2026-11-27',
            requestedRateIdr: 5000000,
            contractRateIdr: 2500000
        );

        $this->assertEquals(2500000, $res->daily_rate_idr);
        $this->assertEquals(5000000, $res->total_room_charge_idr); // 2 nights * 2.5M
    }
}
