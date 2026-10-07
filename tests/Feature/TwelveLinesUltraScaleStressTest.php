<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\TwelveLinesUltraSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Tests\TestCase;

class TwelveLinesUltraScaleStressTest extends TestCase
{
    use RefreshDatabase;

    public function test_twelve_lines_ultra_seeder_runs_idempotently(): void
    {
        $seeder = new TwelveLinesUltraSeeder;

        // Run first time
        $seeder->run();

        $this->assertDatabaseHas('ven_entertainment_venues', ['venue_code' => 'VEN-BALI-CANGGU-01']);
        $this->assertDatabaseHas('htl_properties', ['property_code' => 'HTL-AYANA-RESORT-01']);
        $this->assertDatabaseHas('min_sites', ['site_code' => 'MINE-HALMAHERA-CENTRAL']);

        $venueCount = EntertainmentVenue::count();
        $hotelCount = HotelProperty::count();
        $mineCount = MiningSite::count();

        // Run second time (idempotency check)
        $seeder->run();

        $this->assertEquals($venueCount, EntertainmentVenue::count());
        $this->assertEquals($hotelCount, HotelProperty::count());
        $this->assertEquals($mineCount, MiningSite::count());
    }
}
