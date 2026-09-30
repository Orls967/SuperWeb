<?php

declare(strict_types=1);

namespace Modules\Logistics\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\database\seeders\LogisticsNetworkSeeder;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Models\Lane;
use Modules\Logistics\Domain\Models\Location;
use Tests\TestCase;

class LogisticsNetworkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogisticsNetworkSeeder::class);
    }

    public function test_locations_seeded_properly_with_official_unlocode_and_iata(): void
    {
        // Seaports UN/LOCODE
        $unlocodes = ['IDBDJ', 'IDTPP', 'IDSUB', 'IDBLW', 'IDMAK', 'SGSIN', 'CNSHA'];
        foreach ($unlocodes as $code) {
            $loc = Location::where('unlocode', $code)->first();
            $this->assertNotNull($loc, "Seaport with UN/LOCODE {$code} must exist");
            $this->assertEquals(LocationType::SEAPORT, $loc->type);
        }

        // Airports IATA
        $iatas = ['BDJ', 'CGK', 'SUB', 'UPG', 'BPN', 'KNO', 'SIN', 'PVG'];
        foreach ($iatas as $iata) {
            $loc = Location::where('iata', $iata)->first();
            $this->assertNotNull($loc, "Airport with IATA {$iata} must exist");
            $this->assertEquals(LocationType::AIRPORT, $loc->type);
        }

        // Banjarmasin central hub and coordinates
        $bdjHub = Location::where('code', 'HUB-BDJ')->firstOrFail();
        $this->assertEquals('Banjarmasin', $bdjHub->city);
        $this->assertEquals('Kalimantan Selatan', $bdjHub->province);
        $this->assertEquals(-3.3422, $bdjHub->latitude());
        $this->assertEquals(114.5806, $bdjHub->longitude());

        // CFS and Depots
        $this->assertTrue(Location::where('code', 'CFS-BDJ')->where('type', LocationType::CFS)->exists());
        $this->assertTrue(Location::where('code', 'DEP-BDJ')->where('type', LocationType::DEPOT)->exists());
    }

    public function test_lanes_seeded_connecting_multimodal_network(): void
    {
        // Road connection Banjarmasin Hub to Banjarbaru Hub
        $roadLane = Lane::whereHas('origin', fn ($q) => $q->where('code', 'HUB-BDJ'))
            ->whereHas('destination', fn ($q) => $q->where('code', 'HUB-BJB'))
            ->where('mode', TransportMode::ROAD)
            ->first();
        $this->assertNotNull($roadLane);
        $this->assertEquals(35.0, $roadLane->distanceKm());

        // Sea connection Trisakti to Tanjung Perak
        $seaLane = Lane::whereHas('origin', fn ($q) => $q->where('code', 'PORT-IDBDJ'))
            ->whereHas('destination', fn ($q) => $q->where('code', 'PORT-IDSUB'))
            ->where('mode', TransportMode::SEA)
            ->first();
        $this->assertNotNull($seaLane);
        $this->assertEquals(510.0, $seaLane->distanceKm());
        $this->assertEquals(24.0, $seaLane->standardTransitHours());

        // Air connection BDJ to CGK
        $airLane = Lane::whereHas('origin', fn ($q) => $q->where('code', 'AIRP-BDJ'))
            ->whereHas('destination', fn ($q) => $q->where('code', 'AIRP-CGK'))
            ->where('mode', TransportMode::AIR)
            ->first();
        $this->assertNotNull($airLane);
        $this->assertEquals(950.0, $airLane->distanceKm());
    }

    public function test_location_crud_operations(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Create
        $response = $this->actingAs($admin)->post(route('logistics.locations.store'), [
            'code' => 'HUB-KND',
            'name' => 'Hub Logistik Kandangan HSS',
            'type' => 'hub',
            'city' => 'Kandangan',
            'province' => 'Kalimantan Selatan',
            'country' => 'ID',
            'latitude' => -2.783333,
            'longitude' => 115.250000,
            'timezone' => 'Asia/Makassar',
            'min_connection_minutes' => 45,
        ]);

        $response->assertRedirect(route('logistics.locations.index'));
        $this->assertDatabaseHas('lgx_locations', ['code' => 'HUB-KND', 'lat_e6' => -2783333]);

        $loc = Location::where('code', 'HUB-KND')->firstOrFail();

        // Update
        $this->actingAs($admin)->put(route('logistics.locations.update', $loc), [
            'name' => 'Hub Hulu Sungai Selatan (Kandangan)',
            'type' => 'hub',
            'city' => 'Kandangan',
            'province' => 'Kalimantan Selatan',
            'country' => 'ID',
            'latitude' => -2.783333,
            'longitude' => 115.250000,
            'timezone' => 'Asia/Makassar',
            'min_connection_minutes' => 60,
        ])->assertRedirect(route('logistics.locations.index'));

        $this->assertEquals('Hub Hulu Sungai Selatan (Kandangan)', $loc->fresh()->name);

        // Delete
        $this->actingAs($admin)->delete(route('logistics.locations.destroy', $loc))
            ->assertRedirect(route('logistics.locations.index'));
        $this->assertDatabaseMissing('lgx_locations', ['code' => 'HUB-KND']);
    }

    public function test_lane_crud_and_duplicate_prevention(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $orig = Location::where('code', 'HUB-MTP')->firstOrFail();
        $dest = Location::where('code', 'HUB-PKY')->firstOrFail();

        // Store new lane
        $response = $this->actingAs($dispatcher)->post(route('logistics.lanes.store'), [
            'origin_id' => $orig->id,
            'destination_id' => $dest->id,
            'mode' => 'road',
            'distance_km' => 205.5,
            'standard_transit_minutes' => 330,
        ]);
        $response->assertRedirect(route('logistics.lanes.index'));

        $this->assertDatabaseHas('lgx_lanes', [
            'origin_id' => $orig->id,
            'destination_id' => $dest->id,
            'mode' => 'road',
            'distance_m' => 205500,
        ]);

        // Duplicate attempt fails
        $dupResponse = $this->actingAs($dispatcher)->post(route('logistics.lanes.store'), [
            'origin_id' => $orig->id,
            'destination_id' => $dest->id,
            'mode' => 'road',
            'distance_km' => 205.5,
            'standard_transit_minutes' => 330,
        ]);
        $dupResponse->assertSessionHasErrors('mode');
    }

    public function test_locations_index_renders_svg_schematic_network(): void
    {
        $user = User::factory()->create(['role' => 'logistics_admin']);

        $response = $this->actingAs($user)->get(route('logistics.locations.index'));
        $response->assertOk();
        $response->assertSee('Peta Skematik Jaringan Multimoda Berpusat di Banjarmasin Hub');
        $response->assertSee('PORT-IDBDJ');
        $response->assertSee('AIRP-BDJ');
        $response->assertSee('<svg viewBox="0 0 1000 580"', false);
    }
}
