<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Car;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\AcquiresVehicle;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Enums\TruckType;
use Modules\Logistics\Domain\Models\Aircraft;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Truck;
use Modules\Logistics\Domain\Models\Vessel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hubBdj = Location::create([
        'code' => 'HUB-BDJ',
        'name' => 'Banjarmasin Central Hub',
        'type' => LocationType::HUB,
        'city' => 'Banjarmasin',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3319400,
        'lng_e6' => 114590800,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 60,
    ]);

    $this->hubBjb = Location::create([
        'code' => 'HUB-BJB',
        'name' => 'Banjarbaru Cargo Hub',
        'type' => LocationType::HUB,
        'city' => 'Banjarbaru',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3440900,
        'lng_e6' => 114830400,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 60,
    ]);

    $this->portBdj = Location::create([
        'code' => 'IDBDJ',
        'name' => 'Pelabuhan Trisakti',
        'type' => LocationType::SEAPORT,
        'city' => 'Banjarmasin',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3330000,
        'lng_e6' => 114570000,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 120,
    ]);

    $this->portSub = Location::create([
        'code' => 'IDSUB',
        'name' => 'Pelabuhan Tanjung Perak',
        'type' => LocationType::SEAPORT,
        'city' => 'Surabaya',
        'province' => 'Jawa Timur',
        'country_code' => 'ID',
        'lat_e6' => -7200000,
        'lng_e6' => 112730000,
        'timezone' => 'Asia/Jakarta',
        'min_connection_minutes' => 180,
    ]);

    $this->airportBdj = Location::create([
        'code' => 'BDJ',
        'name' => 'Bandara Syamsudin Noor',
        'type' => LocationType::AIRPORT,
        'city' => 'Banjarbaru',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3440000,
        'lng_e6' => 114750000,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 90,
    ]);

    $this->airportCkg = Location::create([
        'code' => 'CGK',
        'name' => 'Bandara Soekarno-Hatta',
        'type' => LocationType::AIRPORT,
        'city' => 'Tangerang',
        'province' => 'Banten',
        'country_code' => 'ID',
        'lat_e6' => -6125600,
        'lng_e6' => 106655900,
        'timezone' => 'Asia/Jakarta',
        'min_connection_minutes' => 90,
    ]);
});

test('road schedule can be created for truck with driver and multidimensional capacity', function () {
    $admin = User::factory()->create(['role' => 'admin']);
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
        plateNumber: 'DA 8001 TA',
        color: 'White/Orange',
        vin: 'MHFVR34P0NK800101'
    );

    $truck = Truck::create([
        'vehicle_id' => $vehicle->id,
        'plate_number' => $vehicle->plate_number,
        'type' => TruckType::CDD,
        'payload_kg' => 5000,
        'volume_dm3' => 14000,
        'required_license' => TruckType::CDD->requiredLicense(),
        'service_interval_m' => 10_000_000,
        'odometer_m' => 10_000_000,
        'status' => FleetStatus::AVAILABLE,
        'current_location_id' => $this->hubBdj->id,
    ]);

    $driverUser = User::factory()->create();
    $driver = Driver::create([
        'driver_number' => 'DRV-BDJ-001',
        'user_id' => $driverUser->id,
        'license_class' => 'BII',
        'license_expiry' => now()->addYear(),
        'home_hub_id' => $this->hubBdj->id,
        'is_active' => true,
    ]);

    $schedule = Schedule::create([
        'schedule_number' => 'TRP-BDJ-BJB-001',
        'mode' => TransportMode::ROAD,
        'asset_type' => Truck::class,
        'asset_id' => $truck->id,
        'driver_id' => $driver->id,
        'origin_location_id' => $this->hubBdj->id,
        'destination_location_id' => $this->hubBjb->id,
        'etd' => now()->addHours(2),
        'eta' => now()->addHours(4),
        'cutoff_at' => now()->addHour(),
        'status' => ScheduleStatus::Scheduled,
        'cap_weight_kg' => '5000.000',
        'cap_volume_dm3' => 14000,
        'cap_teu' => 0,
        'cap_uld_positions' => 0,
    ]);

    expect($schedule->asset)->toBeInstanceOf(Truck::class)
        ->and($schedule->driver)->toBeInstanceOf(Driver::class)
        ->and($schedule->origin->id)->toBe($this->hubBdj->id)
        ->and($schedule->destination->id)->toBe($this->hubBjb->id)
        ->and($schedule->isPastCutoff())->toBeFalse()
        ->and($schedule->hasAvailableCapacity('2500', 5000))->toBeTrue()
        ->and($schedule->hasAvailableCapacity('5001', 1000))->toBeFalse()
        ->and($schedule->hasAvailableCapacity('1000', 15000))->toBeFalse();
});

test('sea voyage supports multi-port calls sharing voyage_number with leg sequences', function () {
    $vessel = Vessel::create([
        'name' => 'KM Kumala Bahari',
        'imo_number' => '9074729', // valid IMO with check digit
        'flag' => 'ID',
        'dwt_tonnes' => 8000,
        'teu_capacity' => 450,
        'status' => FleetStatus::AVAILABLE,
    ]);

    // Leg 1: Banjarmasin -> Surabaya
    $leg1 = Schedule::create([
        'schedule_number' => 'VOY-KML-01-L1',
        'voyage_number' => 'VOY-KML-01',
        'sequence' => 1,
        'mode' => TransportMode::SEA,
        'asset_type' => Vessel::class,
        'asset_id' => $vessel->id,
        'origin_location_id' => $this->portBdj->id,
        'destination_location_id' => $this->portSub->id,
        'etd' => now()->addDays(1),
        'eta' => now()->addDays(2),
        'cutoff_at' => now()->addHours(18),
        'status' => ScheduleStatus::Scheduled,
        'cap_weight_kg' => '5000000.000',
        'cap_volume_dm3' => 8000000,
        'cap_teu' => 450,
        'cap_uld_positions' => 0,
    ]);

    // Leg 2: Surabaya -> Banjarmasin
    $leg2 = Schedule::create([
        'schedule_number' => 'VOY-KML-01-L2',
        'voyage_number' => 'VOY-KML-01',
        'sequence' => 2,
        'mode' => TransportMode::SEA,
        'asset_type' => Vessel::class,
        'asset_id' => $vessel->id,
        'origin_location_id' => $this->portSub->id,
        'destination_location_id' => $this->portBdj->id,
        'etd' => now()->addDays(3),
        'eta' => now()->addDays(4),
        'cutoff_at' => now()->addDays(2)->addHours(12),
        'status' => ScheduleStatus::Scheduled,
        'cap_weight_kg' => '5000000.000',
        'cap_volume_dm3' => 8000000,
        'cap_teu' => 450,
        'cap_uld_positions' => 0,
    ]);

    $voyageLegs = Schedule::where('voyage_number', 'VOY-KML-01')
        ->orderBy('sequence')
        ->get();

    expect($voyageLegs)->toHaveCount(2)
        ->and($voyageLegs[0]->sequence)->toBe(1)
        ->and($voyageLegs[0]->origin_location_id)->toBe($this->portBdj->id)
        ->and($voyageLegs[1]->sequence)->toBe(2)
        ->and($voyageLegs[1]->destination_location_id)->toBe($this->portBdj->id)
        ->and($voyageLegs[0]->hasAvailableCapacity(1000, 1000, 50))->toBeTrue()
        ->and($voyageLegs[0]->hasAvailableCapacity(1000, 1000, 451))->toBeFalse();
});

test('air cargo flight schedule supports uld positions and volumetric capacity', function () {
    $aircraft = Aircraft::create([
        'registration' => 'PK-CAR',
        'type' => 'B737-800BCF',
        'max_payload_kg' => 23900,
        'uld_positions' => 11,
        'status' => FleetStatus::AVAILABLE,
    ]);

    $flight = Schedule::create([
        'schedule_number' => 'FLT-PKCAR-701',
        'mode' => TransportMode::AIR,
        'asset_type' => Aircraft::class,
        'asset_id' => $aircraft->id,
        'origin_location_id' => $this->airportBdj->id,
        'destination_location_id' => $this->airportCkg->id,
        'etd' => now()->addHours(6),
        'eta' => now()->addHours(8),
        'cutoff_at' => now()->addHours(4),
        'status' => ScheduleStatus::Scheduled,
        'cap_weight_kg' => '23900.000',
        'cap_volume_dm3' => 140000,
        'cap_teu' => 0,
        'cap_uld_positions' => 11,
        'used_uld_positions' => 9,
    ]);

    expect($flight->remainingUldPositions())->toBe(2)
        ->and($flight->hasAvailableCapacity(1000, 2000, 0, 2))->toBeTrue()
        ->and($flight->hasAvailableCapacity(1000, 2000, 0, 3))->toBeFalse();
});

test('schedule accurately calculates remaining capacity with BigDecimal', function () {
    $schedule = Schedule::create([
        'schedule_number' => 'TRP-TEST-001',
        'mode' => TransportMode::ROAD,
        'origin_location_id' => $this->hubBdj->id,
        'destination_location_id' => $this->hubBjb->id,
        'etd' => now()->addHours(1),
        'eta' => now()->addHours(3),
        'cutoff_at' => now()->addMinutes(30),
        'cap_weight_kg' => '1000.750',
        'used_weight_kg' => '250.250',
        'cap_volume_dm3' => 5000,
        'used_volume_dm3' => 1500,
    ]);

    expect($schedule->remainingWeightKg()->compareTo(BigDecimal::of('750.500')))->toBe(0)
        ->and($schedule->remainingVolumeDm3())->toBe(3500);
});

test('schedule detects overlapping time windows', function () {
    $schedule = Schedule::create([
        'schedule_number' => 'TRP-WINDOW-001',
        'mode' => TransportMode::ROAD,
        'origin_location_id' => $this->hubBdj->id,
        'destination_location_id' => $this->hubBjb->id,
        'etd' => now()->addHours(10),
        'eta' => now()->addHours(14),
        'cutoff_at' => now()->addHours(8),
        'cap_weight_kg' => '1000.000',
        'cap_volume_dm3' => 5000,
    ]);

    // Overlapping window (11 to 15)
    expect($schedule->overlapsWith(now()->addHours(11), now()->addHours(15)))->toBeTrue()
        // Non-overlapping before (5 to 9)
        ->and($schedule->overlapsWith(now()->addHours(5), now()->addHours(9)))->toBeFalse()
        // Non-overlapping after (15 to 18)
        ->and($schedule->overlapsWith(now()->addHours(15), now()->addHours(18)))->toBeFalse();
});
