<?php

declare(strict_types=1);

namespace Modules\Hotel\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;

class HotelDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create hotel property
        $property = HotelProperty::firstOrCreate(
            ['property_code' => 'HTL-JKT-01'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Grand Nusantara Hotel Jakarta',
                'property_type' => 'FULL_SERVICE',
                'city' => 'Jakarta',
                'total_rooms' => 200,
            ]
        );

        // 2. Create rooms
        $room1 = HotelRoom::firstOrCreate(
            ['room_number' => 'DLX-501'],
            [
                'id' => (string) Str::uuid(),
                'property_id' => $property->id,
                'room_type' => 'deluxe',
                'base_rate_idr' => 1500000,
                'status' => 'available',
                'energy_setback_active' => true,
            ]
        );

        $room2 = HotelRoom::firstOrCreate(
            ['room_number' => 'SUI-801'],
            [
                'id' => (string) Str::uuid(),
                'property_id' => $property->id,
                'room_type' => 'suite',
                'base_rate_idr' => 5000000,
                'status' => 'available',
                'energy_setback_active' => true,
            ]
        );

        // 3. Create reservation (checked_in state)
        $reservation = HotelReservation::firstOrCreate(
            ['reservation_number' => 'RSV-JKT-001'],
            [
                'id' => (string) Str::uuid(),
                'property_id' => $property->id,
                'room_id' => $room1->id,
                'guest_user_id' => '1',
                'check_in_date' => now()->toDateString(),
                'check_out_date' => now()->addDays(3)->toDateString(),
                'daily_rate_idr' => 1500000,
                'total_room_charge_idr' => 4500000,
                'status' => 'checked_in',
            ]
        );

        // Mark room as occupied to match reservation
        $room1->update(['status' => 'occupied']);

        // 4. Create folio
        HotelFolio::firstOrCreate(
            ['reservation_id' => $reservation->id],
            [
                'id' => (string) Str::uuid(),
                'guest_user_id' => '1',
                'total_room_charges' => 4500000,
                'total_addon_charges' => 500000,
                'total_paid' => 0,
                'outstanding_balance' => 5000000,
                'status' => 'open',
            ]
        );
    }
}
