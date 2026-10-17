<?php

declare(strict_types=1);

namespace Modules\Venue\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;

class VenueDemoSeeder extends Seeder
{
    public function run(): void
    {
        $venue = EntertainmentVenue::firstOrCreate(
            ['venue_code' => 'VEN-ATLAS-01'],
            [
                'name' => 'Atlas Beach Fest Bali',
                'venue_type' => 'BEACH_CLUB',
                'city' => 'Badung, Bali',
                'min_age_requirement' => 18,
                'max_legal_capacity' => 5000,
            ]
        );

        $event = EntertainmentEvent::firstOrCreate(
            ['event_code' => 'EVT-BALI-SUNSET-01'],
            [
                'venue_id' => $venue->id,
                'title' => 'Bali Summer Sound Festival',
                'doors_open_at' => now()->addDays(7),
                'ticket_floor_price_idr' => 250000,
                'ticket_ceiling_price_idr' => 1500000,
                'current_ticket_price_idr' => 750000,
            ]
        );

        EntertainmentZone::firstOrCreate(
            ['zone_code' => 'ZN-POOL-01'],
            [
                'venue_id' => $venue->id,
                'name' => 'Lagoon Pool Deck',
                'capacity_limit' => 300,
                'current_occupancy' => 45,
                'crowd_lockdown_active' => false,
            ]
        );

        EntertainmentTicket::firstOrCreate(
            ['ticket_number' => 'TCK-DEMO-001'],
            [
                'event_id' => $event->id,
                'buyer_user_id' => 1,
                'price_paid_idr' => 750000,
                'ticket_hash' => hash('sha256', 'HASH-TCK-DEMO-001'),
                'status' => 'VALID',
            ]
        );
    }
}
