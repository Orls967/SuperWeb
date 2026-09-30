<?php

declare(strict_types=1);

namespace Modules\Logistics\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\AutoDex\Domain\Models\Brand;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Core\Contracts\AcquiresVehicle;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Logistics\database\seeders\LogisticsNetworkSeeder;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Enums\TrailerType;
use Modules\Logistics\Domain\Enums\TruckType;
use Modules\Logistics\Domain\Models\Aircraft;
use Modules\Logistics\Domain\Models\Container;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Trailer;
use Modules\Logistics\Domain\Models\Truck;
use Modules\Logistics\Domain\Models\Uld;
use Modules\Logistics\Domain\Models\Vessel;
use Tests\TestCase;

class LogisticsFleetTest extends TestCase
{
    use RefreshDatabase;

    protected Location $hubBdj;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogisticsNetworkSeeder::class);
        $this->hubBdj = Location::where('code', 'HUB-BDJ')->firstOrFail();
    }

    public function test_fleet_status_transitions(): void
    {
        $this->assertTrue(FleetStatus::AVAILABLE->canTransitionTo(FleetStatus::ASSIGNED));
        $this->assertTrue(FleetStatus::AVAILABLE->canTransitionTo(FleetStatus::MAINTENANCE));
        $this->assertTrue(FleetStatus::AVAILABLE->canTransitionTo(FleetStatus::RETIRED));

        $this->assertTrue(FleetStatus::ASSIGNED->canTransitionTo(FleetStatus::IN_TRANSIT));
        $this->assertTrue(FleetStatus::ASSIGNED->canTransitionTo(FleetStatus::AVAILABLE));

        $this->assertTrue(FleetStatus::IN_TRANSIT->canTransitionTo(FleetStatus::AVAILABLE));
        $this->assertTrue(FleetStatus::IN_TRANSIT->canTransitionTo(FleetStatus::MAINTENANCE));

        $this->assertTrue(FleetStatus::MAINTENANCE->canTransitionTo(FleetStatus::AVAILABLE));
        $this->assertFalse(FleetStatus::RETIRED->canTransitionTo(FleetStatus::AVAILABLE));
    }

    public function test_truck_creation_and_vehicle_passport_linkage(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Create base vehicle in Core
        $brand = Brand::firstOrCreate(
            ['slug' => 'isuzu'],
            ['name' => 'Isuzu', 'country' => 'Japan', 'category' => 'other', 'is_active' => true]
        );
        $car = Car::firstOrCreate(
            ['slug' => 'isuzu-giga-fvr'],
            ['brand_id' => $brand->id, 'model' => 'Giga FVR', 'year_start' => 2022, 'body_type' => 'Truck', 'fuel_type' => 'diesel', 'price_idr' => 750000000, 'is_active' => true]
        );

        $vehicle = app(AcquiresVehicle::class)->handle(
            user: $admin,
            car: $car,
            plateNumber: 'DA 8901 SX',
            color: 'White/Orange',
            vin: 'MHFVR34P0NK000123'
        );

        // Passport events should be generated
        $this->assertGreaterThanOrEqual(1, $vehicle->events()->count());
        $this->assertEquals('DA 8901 SX', $vehicle->plate_number);

        // Create Truck
        $truck = Truck::create([
            'vehicle_id' => $vehicle->id,
            'plate_number' => $vehicle->plate_number,
            'type' => TruckType::FUSO,
            'payload_kg' => 8_000,
            'volume_dm3' => 28_000,
            'required_license' => TruckType::FUSO->requiredLicense(),
            'service_interval_m' => 10_000_000,
            'odometer_m' => 50_000_000,
            'status' => FleetStatus::AVAILABLE,
            'current_location_id' => $this->hubBdj->id,
        ]);

        $this->assertDatabaseHas('lgx_trucks', ['plate_number' => 'DA 8901 SX']);
        $this->assertEquals('SIM B1 Umum', $truck->required_license);
        $this->assertTrue(str_starts_with($truck->plate_number, 'DA'));
        $this->assertEquals($vehicle->id, $truck->vehicle->id);
        $this->assertTrue($truck->canTransitionTo(FleetStatus::ASSIGNED));
    }

    public function test_vessel_creation_enforces_imo_check_digit(): void
    {
        // Valid IMO (e.g. 9074729)
        $vessel = Vessel::create([
            'imo_number' => '9074729',
            'name' => 'KM Sari Ranah Maritim I',
            'flag' => 'ID',
            'teu_capacity' => 650,
            'reefer_plugs' => 80,
            'dwt_tonnes' => 12_500,
            'status' => FleetStatus::AVAILABLE,
            'current_location_id' => Location::where('code', 'PORT-IDBDJ')->firstOrFail()->id,
        ]);

        $this->assertEquals('9074729', $vessel->imo_number);

        // Invalid IMO throws exception on save
        $this->expectException(InvalidArgumentException::class);
        Vessel::create([
            'imo_number' => '9074728', // check digit salah
            'name' => 'KM Invalid Vessel',
            'flag' => 'ID',
            'teu_capacity' => 100,
            'dwt_tonnes' => 2000,
            'status' => FleetStatus::AVAILABLE,
        ]);
    }

    public function test_container_creation_enforces_iso_6346_check_digit(): void
    {
        // Valid ISO 6346 Container (e.g. CSQU3054383)
        $container = Container::create([
            'container_number' => 'CSQU3054383',
            'size_type' => '22G1',
            'tare_kg' => 2_200,
            'max_gross_kg' => 30_480,
            'status' => FleetStatus::AVAILABLE,
            'current_location_id' => Location::where('code', 'DEP-BDJ')->firstOrFail()->id,
        ]);

        $this->assertEquals('CSQU3054383', $container->container_number);
        $this->assertEquals(28_280, $container->payloadCapacityKg());

        // Invalid ISO 6346 Container throws exception
        $this->expectException(InvalidArgumentException::class);
        Container::create([
            'container_number' => 'CSQU3054384', // check digit salah
            'size_type' => '22G1',
            'tare_kg' => 2_200,
            'max_gross_kg' => 30_480,
            'status' => FleetStatus::AVAILABLE,
        ]);
    }

    public function test_aircraft_trailer_and_uld_creation(): void
    {
        // Aircraft
        $aircraft = Aircraft::create([
            'registration' => 'PK-SRA',
            'type' => 'B737-800BCF',
            'max_payload_kg' => 23_900,
            'uld_positions' => 12,
            'status' => FleetStatus::AVAILABLE,
            'current_location_id' => Location::where('code', 'AIRP-BDJ')->firstOrFail()->id,
        ]);
        $this->assertEquals('PK-SRA', $aircraft->registration);

        // Trailer
        $trailer = Trailer::create([
            'code' => 'TRL-SK-40-001',
            'type' => TrailerType::SKELETAL_40,
            'payload_kg' => 34_000,
            'status' => FleetStatus::AVAILABLE,
            'current_location_id' => $this->hubBdj->id,
        ]);
        $this->assertEquals('TRL-SK-40-001', $trailer->code);

        // ULD
        $uld = Uld::create([
            'uld_code' => 'PMC-1001-SR',
            'type' => 'PMC',
            'tare_kg' => 120,
            'max_gross_kg' => 6_800,
            'status' => FleetStatus::AVAILABLE,
            'current_location_id' => Location::where('code', 'AIRP-BDJ')->firstOrFail()->id,
        ]);
        $this->assertEquals(6_680, $uld->payloadCapacityKg());
    }

    public function test_fleet_index_page_is_accessible(): void
    {
        $user = User::factory()->create(['role' => 'dispatcher']);

        $response = $this->actingAs($user)->get(route('logistics.fleet.index'));
        $response->assertOk();
        $response->assertViewIs('logistics::fleet.index');
        $response->assertSee('Manajemen Armada Multimoda');
        $response->assertSee('Armada Truk Darat');
        $response->assertSee('Kapal Kargo (IMO Verified)');
    }
}
