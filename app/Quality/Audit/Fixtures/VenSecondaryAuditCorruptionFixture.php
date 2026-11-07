<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Venue\Domain\Models\DynamicBundlePackage;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;

final class VenSecondaryAuditCorruptionFixture implements CorruptionFixture
{
    public function __construct(
        private readonly string $commandName = 'venue:audit-secondary'
    ) {}

    public function command(): string
    {
        return $this->commandName;
    }

    public function corrupt(): string
    {
        $venue = EntertainmentVenue::query()->firstOrCreate(
            ['venue_code' => 'VEN-CORRUPT-TEST'],
            [
                'name' => 'Corrupt Test Venue',
                'venue_type' => 'CLUB',
                'city' => 'Jakarta',
                'min_age_requirement' => 21,
                'max_legal_capacity' => 1000,
            ]
        );

        $event = EntertainmentEvent::query()->firstOrCreate(
            ['event_code' => 'EVT-CORRUPT-TEST'],
            [
                'venue_id' => $venue->id,
                'title' => 'Corrupt Test Event',
                'doors_open_at' => now(),
                'ticket_floor_price_idr' => 100000,
                'ticket_ceiling_price_idr' => 200000,
                'current_ticket_price_idr' => 150000,
                'status' => 'ON_SALE',
            ]
        );

        DynamicBundlePackage::query()->create([
            'package_code' => 'BDL-CORRUPT-99',
            'event_id' => $event->id,
            'package_name' => 'Corrupt Bundle',
            'base_price_idr' => 100000,
            'floor_cost_idr' => 500000,
            'final_price_idr' => 50000, // Below floor cost!
            'status' => 'PUBLISHED',
        ]);

        return 'AUDIT PASAR SEKUNDER VENUE GAGAL';
    }
}
