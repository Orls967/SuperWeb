<?php

declare(strict_types=1);

namespace Modules\Hotel\database\seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HotelBusinessDate;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRateCalendar;
use Modules\Hotel\Domain\Models\HotelRatePlan;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomType;

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
                'tax_pbjt_bps' => 1000,
                'service_charge_bps' => 1000,
            ]
        );

        // 1b. Inisialisasi business date jika belum ada
        HotelBusinessDate::firstOrCreate(
            ['property_id' => $property->id],
            [
                'id' => (string) Str::uuid(),
                'current_business_date' => Carbon::now()->toDateString(),
                'status' => 'open',
            ]
        );

        // 2. Create room types
        $deluxeType = HotelRoomType::firstOrCreate(
            ['property_id' => $property->id, 'code' => 'DELUXE'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Deluxe Room',
                'base_capacity' => 2,
                'overbooking_max_percent' => 5,
            ]
        );

        $suiteType = HotelRoomType::firstOrCreate(
            ['property_id' => $property->id, 'code' => 'SUITE'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Executive Suite',
                'base_capacity' => 4,
                'overbooking_max_percent' => 0,
            ]
        );

        // 3. Create rooms
        $room1 = HotelRoom::firstOrCreate(
            ['room_number' => 'DLX-501'],
            [
                'id' => (string) Str::uuid(),
                'property_id' => $property->id,
                'room_type_id' => $deluxeType->id,
                'room_type' => 'deluxe',
                'base_rate_idr' => 1500000,
                'status' => 'available',
                'energy_setback_active' => true,
            ]
        );
        if (! $room1->room_type_id) {
            $room1->update(['room_type_id' => $deluxeType->id]);
        }

        $room2 = HotelRoom::firstOrCreate(
            ['room_number' => 'SUI-801'],
            [
                'id' => (string) Str::uuid(),
                'property_id' => $property->id,
                'room_type_id' => $suiteType->id,
                'room_type' => 'suite',
                'base_rate_idr' => 5000000,
                'status' => 'available',
                'energy_setback_active' => true,
            ]
        );
        if (! $room2->room_type_id) {
            $room2->update(['room_type_id' => $suiteType->id]);
        }

        // 4. Rate Plans
        $barPlan = HotelRatePlan::firstOrCreate(
            ['property_id' => $property->id, 'code' => 'BAR'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Best Available Rate Breakfast',
                'plan_type' => 'BAR',
                'free_cancellation_hours' => 24,
                'penalty_nights' => 1,
                'requires_deposit' => false,
            ]
        );

        $nonRefPlan = HotelRatePlan::firstOrCreate(
            ['property_id' => $property->id, 'code' => 'NON_REFUNDABLE'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Non Refundable Promotion',
                'plan_type' => 'NON_REFUNDABLE',
                'free_cancellation_hours' => 0,
                'penalty_nights' => 1,
                'requires_deposit' => true,
            ]
        );

        // 5. Rate Calendar (30 hari ke depan)
        $today = Carbon::now();
        for ($i = 0; $i < 30; $i++) {
            $targetDate = $today->copy()->addDays($i)->toDateString();
            HotelRateCalendar::firstOrCreate(
                [
                    'property_id' => $property->id,
                    'room_type_id' => $deluxeType->id,
                    'rate_plan_id' => $barPlan->id,
                    'calendar_date' => $targetDate,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'rate_idr' => 1500000,
                ]
            );

            HotelRateCalendar::firstOrCreate(
                [
                    'property_id' => $property->id,
                    'room_type_id' => $deluxeType->id,
                    'rate_plan_id' => $nonRefPlan->id,
                    'calendar_date' => $targetDate,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'rate_idr' => 1200000,
                ]
            );
        }

        // 6. Create reservation (checked_in state)
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

        // 7. Create folio
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
