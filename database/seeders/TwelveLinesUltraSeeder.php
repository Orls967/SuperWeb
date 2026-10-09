<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;

class TwelveLinesUltraSeeder extends Seeder
{
    /**
     * Seed realistic scale demo data for the newly added lines:
     * - Venue & Clubs
     * - Hotel & Hospitality
     * - Mining & Heavy Fleet
     */
    public function run(): void
    {
        // 1. Seed Venues
        $venue = EntertainmentVenue::firstOrCreate(
            ['venue_code' => 'VEN-BALI-CANGGU-01'],
            [
                'name' => 'Finns Beach Club Bali',
                'venue_type' => 'BEACH_CLUB',
                'city' => 'Badung',
                'max_legal_capacity' => 5000,
                'min_age_requirement' => 21,
            ]
        );

        $zone = EntertainmentZone::firstOrCreate(
            ['venue_id' => $venue->id, 'zone_code' => 'ZN-VIP-SUNBEDS'],
            [
                'name' => 'VIP Oceanfront Daybeds',
                'capacity_limit' => 250,
                'current_occupancy' => 45,
            ]
        );

        EntertainmentEvent::firstOrCreate(
            ['event_code' => 'EVT-GLOBAL-SUNSET-FEST'],
            [
                'venue_id' => $venue->id,
                'title' => 'Global Sunset EDM Festival 2026',
                'doors_open_at' => now()->addDays(5),
                'ticket_floor_price_idr' => 350000,
                'ticket_ceiling_price_idr' => 2500000,
                'current_ticket_price_idr' => 750000,
            ]
        );

        // 2. Seed Hotel
        $hotel = HotelProperty::firstOrCreate(
            ['property_code' => 'HTL-AYANA-RESORT-01'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Ayana Resort & Villas Jimbaran',
                'property_type' => 'RESORT',
                'city' => 'Badung',
                'total_rooms' => 500,
            ]
        );

        HotelRoom::firstOrCreate(
            ['property_id' => $hotel->id, 'room_number' => 'CLIFF-VILLA-01'],
            [
                'id' => (string) Str::uuid(),
                'room_type' => 'CLIFF_VILLA',
                'base_rate_idr' => 6500000,
                'status' => 'available',
                'energy_setback_active' => true,
            ]
        );

        // 3. Seed Mining Site
        $miningSite = MiningSite::firstOrCreate(
            ['site_code' => 'MINE-HALMAHERA-CENTRAL'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Halmahera Nickel & Cobalt Complex',
                'commodity' => 'NICKEL',
                'location' => 'North Maluku',
            ]
        );

        MiningPit::firstOrCreate(
            ['site_id' => $miningSite->id, 'pit_code' => 'PIT-KABAENA-EAST'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Kabaena East High-Grade Nickel Pit',
                'target_production_ton' => 150000,
                'actual_production_ton' => 45000,
            ]
        );

        MiningEquipment::firstOrCreate(
            ['equipment_code' => 'KOM-HD785-001'],
            [
                'id' => (string) Str::uuid(),
                'site_id' => $miningSite->id,
                'type' => 'HAUL_TRUCK',
                'model' => 'Komatsu HD785-7 100-ton',
                'capacity_ton' => 100.00,
                'status' => 'available',
                'engine_hours' => 3400,
            ]
        );
    }
}
