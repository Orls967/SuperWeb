<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Str;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;

final class VenAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ven:audit';
    }

    public function corrupt(): string
    {
        $venue = EntertainmentVenue::firstOrCreate(
            ['venue_code' => 'VEN-CORRUPT-01'],
            [
                'name' => 'Corrupt Beach Club',
                'venue_type' => 'BEACH_CLUB',
                'city' => 'Seminyak, Bali',
                'min_age_requirement' => 21,
                'max_legal_capacity' => 1000,
            ]
        );

        EntertainmentEvent::firstOrCreate(
            ['event_code' => 'EVT-CORRUPT-01'],
            [
                'venue_id' => $venue->id,
                'title' => 'Corrupted Sunset Festival',
                'doors_open_at' => now()->addDays(5),
                'ticket_floor_price_idr' => 100000,
                'ticket_ceiling_price_idr' => 1000000,
                'current_ticket_price_idr' => 500000,
            ]
        );

        // Corrupt zone state: current_occupancy exceeds capacity_limit
        EntertainmentZone::create([
            'zone_code' => 'ZN-CORRUPT-'.Str::random(4),
            'venue_id' => $venue->id,
            'name' => 'Overcapacity Dance Floor',
            'capacity_limit' => 50,
            'current_occupancy' => 85, // Discrepancy: 85 > 50!
            'crowd_lockdown_active' => false,
        ]);

        return 'Venue audit FAILED';
    }
}
