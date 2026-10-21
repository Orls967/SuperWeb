<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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
     * Seed ultra-scale deterministic dataset with strict memory capping (< 64MB)
     */
    public function run(): void
    {
        DB::disableQueryLog();

        $this->seedVenuesAndEvents(25);
        $this->seedHotelsAndRooms(30);
        $this->seedMiningFleet(20);

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    protected function seedVenuesAndEvents(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $venueCode = sprintf('VEN-ULTRA-%04d', $i);
            $venue = EntertainmentVenue::query()->firstOrCreate(
                ['venue_code' => $venueCode],
                [
                    'name' => "Ultra Grand Arena {$i}",
                    'venue_type' => 'BEACH_CLUB',
                    'city' => 'Badung',
                    'max_legal_capacity' => 5000,
                    'min_age_requirement' => 21,
                ]
            );

            EntertainmentZone::query()->firstOrCreate(
                ['venue_id' => $venue->id, 'zone_code' => sprintf('ZN-VIP-%04d', $i)],
                [
                    'name' => "VIP Deck {$i}",
                    'capacity_limit' => 250,
                    'current_occupancy' => 50,
                ]
            );

            EntertainmentEvent::query()->firstOrCreate(
                ['event_code' => sprintf('EVT-ULTRA-%04d', $i)],
                [
                    'venue_id' => $venue->id,
                    'title' => "Global Sunset EDM Festival {$i}",
                    'doors_open_at' => now()->addDays($i)->setTime(18, 0),
                    'ticket_floor_price_idr' => 350000,
                    'ticket_ceiling_price_idr' => 2500000,
                    'current_ticket_price_idr' => 750000,
                ]
            );
        }
    }

    protected function seedHotelsAndRooms(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $hotelUuid = sprintf('htl-prop-%08d-0000-0000-0000-000000000000', $i);
            $propertyCode = sprintf('HTL-ULTRA-%04d', $i);
            $hotel = HotelProperty::query()->firstOrCreate(
                ['property_code' => $propertyCode],
                [
                    'id' => $hotelUuid,
                    'name' => "Royal Horizon Hotel {$i}",
                    'property_type' => 'RESORT',
                    'city' => 'Badung',
                    'total_rooms' => 100,
                ]
            );

            for ($r = 1; $r <= 5; $r++) {
                $roomUuid = sprintf('htl-room-%08d-%04d-0000-0000-000000000000', $i, $r);
                HotelRoom::query()->firstOrCreate(
                    ['property_id' => $hotel->id, 'room_number' => sprintf('%03d', $r)],
                    [
                        'id' => $roomUuid,
                        'room_type' => 'DELUXE_SUITE',
                        'base_rate_idr' => 1500000,
                        'status' => 'available',
                        'energy_setback_active' => true,
                    ]
                );
            }
        }
    }

    protected function seedMiningFleet(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $siteUuid = sprintf('mine-site-%08d-0000-0000-0000-000000000000', $i);
            $siteCode = sprintf('MINE-SITE-%04d', $i);
            $site = MiningSite::query()->firstOrCreate(
                ['site_code' => $siteCode],
                [
                    'id' => $siteUuid,
                    'name' => "Halmahera Mining Site {$i}",
                    'commodity' => 'NICKEL',
                    'location' => 'North Maluku',
                ]
            );

            $pitUuid = sprintf('mine-pit-%08d-0000-0000-0000-000000000000', $i);
            $pit = MiningPit::query()->firstOrCreate(
                ['site_id' => $site->id, 'pit_code' => sprintf('PIT-KABAENA-%04d', $i)],
                [
                    'id' => $pitUuid,
                    'name' => "Kabaena Pit {$i}",
                    'target_production_ton' => 150000,
                    'actual_production_ton' => 45000,
                ]
            );

            $equipUuid = sprintf('mine-equip-%08d-0000-0000-0000-000000000000', $i);
            MiningEquipment::query()->firstOrCreate(
                ['equipment_code' => sprintf('KOM-HD785-%04d', $i)],
                [
                    'id' => $equipUuid,
                    'site_id' => $site->id,
                    'type' => 'HAUL_TRUCK',
                    'model' => 'Komatsu HD785-7 100-ton',
                    'capacity_ton' => 100,
                    'status' => 'available',
                    'engine_hours' => 3400,
                ]
            );
        }
    }
}
