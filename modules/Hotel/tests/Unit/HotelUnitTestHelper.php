<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Unit;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HotelBusinessDate;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRateCalendar;
use Modules\Hotel\Domain\Models\HotelRatePlan;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomType;

trait HotelUnitTestHelper
{
    protected HotelProperty $property;

    protected User $guest;

    protected User $staff;

    protected HotelRoomType $roomType;

    protected HotelRatePlan $barPlan;

    protected HotelRoom $room;

    protected function setupHotelFixture(): void
    {
        $this->property = HotelProperty::firstOrCreate(
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

        HotelBusinessDate::firstOrCreate(
            ['property_id' => $this->property->id],
            [
                'id' => (string) Str::uuid(),
                'current_business_date' => Carbon::now()->toDateString(),
                'status' => 'open',
            ]
        );

        $this->guest = User::factory()->create(['role' => 'guest']);
        $this->staff = User::factory()->create(['role' => 'front_office']);

        $this->roomType = HotelRoomType::firstOrCreate(
            ['property_id' => $this->property->id, 'code' => 'DELUXE'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Deluxe Room',
                'base_capacity' => 2,
                'overbooking_max_percent' => 5,
            ]
        );

        $suiteType = HotelRoomType::firstOrCreate(
            ['property_id' => $this->property->id, 'code' => 'SUITE'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Executive Suite',
                'base_capacity' => 4,
                'overbooking_max_percent' => 0,
            ]
        );

        $this->barPlan = HotelRatePlan::firstOrCreate(
            ['property_id' => $this->property->id, 'code' => 'BAR'],
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
            ['property_id' => $this->property->id, 'code' => 'NON_REFUNDABLE'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Non Refundable Promotion',
                'plan_type' => 'NON_REFUNDABLE',
                'free_cancellation_hours' => 0,
                'penalty_nights' => 1,
                'requires_deposit' => true,
            ]
        );

        $this->room = HotelRoom::firstOrCreate(
            ['room_number' => 'DLX-501'],
            [
                'id' => (string) Str::uuid(),
                'property_id' => $this->property->id,
                'room_type_id' => $this->roomType->id,
                'room_type' => 'deluxe',
                'base_rate_idr' => 1500000,
                'status' => 'available',
                'cleaning_status' => 'inspected',
                'physical_status' => 'operational',
                'energy_setback_active' => true,
                'is_active' => true,
            ]
        );

        $room2 = HotelRoom::firstOrCreate(
            ['room_number' => 'SUI-801'],
            [
                'id' => (string) Str::uuid(),
                'property_id' => $this->property->id,
                'room_type_id' => $suiteType->id,
                'room_type' => 'suite',
                'base_rate_idr' => 5000000,
                'status' => 'available',
                'cleaning_status' => 'inspected',
                'physical_status' => 'operational',
                'energy_setback_active' => true,
                'is_active' => true,
            ]
        );

        // Rate calendar 14 hari
        $today = Carbon::now();
        for ($i = 0; $i < 14; $i++) {
            $targetDate = $today->copy()->addDays($i)->toDateString();
            HotelRateCalendar::firstOrCreate(
                [
                    'property_id' => $this->property->id,
                    'room_type_id' => $this->roomType->id,
                    'rate_plan_id' => $this->barPlan->id,
                    'calendar_date' => $targetDate,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'rate_idr' => 1500000,
                ]
            );

            HotelRateCalendar::firstOrCreate(
                [
                    'property_id' => $this->property->id,
                    'room_type_id' => $this->roomType->id,
                    'rate_plan_id' => $nonRefPlan->id,
                    'calendar_date' => $targetDate,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'rate_idr' => 1200000,
                ]
            );
        }

        // Default reservation (checked_in state) and folio
        $reservation = HotelReservation::firstOrCreate(
            ['reservation_number' => 'RSV-JKT-001'],
            [
                'id' => (string) Str::uuid(),
                'property_id' => $this->property->id,
                'room_id' => $this->room->id,
                'guest_user_id' => $this->guest->id,
                'check_in_date' => now()->toDateString(),
                'check_out_date' => now()->addDays(3)->toDateString(),
                'daily_rate_idr' => 1500000,
                'total_room_charge_idr' => 4500000,
                'status' => 'checked_in',
            ]
        );

        $this->room->update(['status' => 'occupied']);

        HotelFolio::firstOrCreate(
            ['reservation_id' => $reservation->id],
            [
                'id' => (string) Str::uuid(),
                'guest_user_id' => $this->guest->id,
                'total_room_charges' => 4500000,
                'total_addon_charges' => 500000,
                'total_paid' => 0,
                'outstanding_balance' => 5000000,
                'status' => 'open',
            ]
        );

        $this->seed(LedgerAccountsSeeder::class);
    }
}
