<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Carbon\Carbon;
use Modules\Proptech\Domain\Models\Flex\FlexBooking;
use Modules\Proptech\Domain\Models\Flex\FlexSpace;

final class ProptechFlexCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'prp:audit-flex';
    }

    public function corrupt(): string
    {
        $space = FlexSpace::firstOrCreate(
            ['space_code' => 'SPC-CORRUPT-FLX-01'],
            [
                'name' => 'Corrupted Overlap Space',
                'space_type' => 'MEETING_ROOM',
                'capacity_persons' => 8,
                'rate_per_hour_idr' => 150000,
                'status' => 'AVAILABLE',
            ]
        );

        $now = Carbon::now();

        FlexBooking::create([
            'booking_code' => 'FBK-CORRUPT-OVERLAP-01',
            'flex_space_id' => $space->id,
            'user_id' => 901,
            'start_time' => $now->copy()->addHours(2),
            'end_time' => $now->copy()->addHours(5),
            'deposit_amount_idr' => 135000,
            'total_price_idr' => 450000,
            'status' => 'HELD',
        ]);

        // Second booking overlaps with the first one
        FlexBooking::create([
            'booking_code' => 'FBK-CORRUPT-OVERLAP-02',
            'flex_space_id' => $space->id,
            'user_id' => 902,
            'start_time' => $now->copy()->addHours(3),
            'end_time' => $now->copy()->addHours(6),
            'deposit_amount_idr' => 135000,
            'total_price_idr' => 450000,
            'status' => 'HELD',
        ]);

        return 'FBK-CORRUPT-OVERLAP-02';
    }
}
