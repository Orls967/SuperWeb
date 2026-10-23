<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HotelRoom;

final class HtlAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'htl:audit';
    }

    public function corrupt(): string
    {
        // Corrupt: room marked 'occupied' but no matching checked_in reservation
        HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => (string) Str::uuid(),
            'room_number' => 'CORRUPT-'.Str::random(4),
            'room_type' => 'deluxe',
            'base_rate_idr' => 1000000,
            'status' => 'occupied',
            'energy_setback_active' => false,
        ]);

        return 'Hotel audit FAILED';
    }
}
