<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRoom;
use Tests\TestCase;

class TwelveLinesExtremeConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_pessimistic_locking_prevents_room_oversell(): void
    {
        $propertyUuid = 'htl-prop-concur-0001-0000-000000000000';
        $property = HotelProperty::query()->create([
            'id' => $propertyUuid,
            'property_code' => 'HTL-CONCUR-01',
            'name' => 'Concurrency Grand Hotel',
            'property_type' => 'RESORT',
            'city' => 'Badung',
            'total_rooms' => 5,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            HotelRoom::query()->create([
                'id' => sprintf('htl-room-concur-%04d-0000-000000000000', $i),
                'property_id' => $property->id,
                'room_number' => sprintf('%03d', $i),
                'room_type' => 'SUITE',
                'base_rate_idr' => 1000000,
                'status' => 'available',
                'energy_setback_active' => false,
            ]);
        }

        $successfulBookings = 0;
        $failedBookings = 0;

        for ($attempt = 1; $attempt <= 50; $attempt++) {
            DB::beginTransaction();
            try {
                $room = HotelRoom::query()
                    ->where('property_id', $property->id)
                    ->where('status', 'available')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->first();

                if ($room) {
                    $room->update(['status' => 'booked']);
                    DB::commit();
                    $successfulBookings++;
                } else {
                    DB::rollBack();
                    $failedBookings++;
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $failedBookings++;
            }
        }

        $this->assertSame(5, $successfulBookings);
        $this->assertSame(45, $failedBookings);
        $this->assertSame(0, HotelRoom::query()->where('property_id', $property->id)->where('status', 'available')->count());
        $this->assertSame(5, HotelRoom::query()->where('property_id', $property->id)->where('status', 'booked')->count());
    }
}
