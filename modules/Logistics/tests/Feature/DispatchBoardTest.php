<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\AcquiresVehicle;
use Modules\Core\Domain\Models\VehicleEvent;
use Modules\Logistics\Application\Actions\AssignScheduleResourcesAction;
use Modules\Logistics\Application\Actions\AssignShipmentToDriverAction;
use Modules\Logistics\Application\Actions\ReleaseScheduleResourcesAction;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Enums\TruckType;
use Modules\Logistics\Domain\Exceptions\DriverLicenseExpiredException;
use Modules\Logistics\Domain\Exceptions\DriverNotAssignableException;
use Modules\Logistics\Domain\Exceptions\DrivingHoursLimitExceededException;
use Modules\Logistics\Domain\Exceptions\IncompatibleLicenseException;
use Modules\Logistics\Domain\Exceptions\InvalidDispatchException;
use Modules\Logistics\Domain\Exceptions\InvalidVehiclePassportException;
use Modules\Logistics\Domain\Exceptions\ScheduleConflictException;
use Modules\Logistics\Domain\Exceptions\TruckNotAssignableException;
use Modules\Logistics\Domain\Models\DispatchAssignment;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\Truck;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;

uses(RefreshDatabase::class);

beforeEach(function () {
    $mk = fn (string $code, string $name, string $city) => Location::create([
        'code' => $code, 'name' => $name, 'type' => LocationType::HUB, 'city' => $city,
        'province' => 'Kalimantan Selatan', 'country_code' => 'ID', 'lat_e6' => -3300000, 'lng_e6' => 114500000,
        'timezone' => 'Asia/Makassar', 'min_connection_minutes' => 60,
    ]);
    $this->hubA = $mk('HUB-BDJ', 'Hub Banjarmasin', 'Banjarmasin');
    $this->hubB = $mk('HUB-BJB', 'Hub Banjarbaru', 'Banjarbaru');
    $this->dispatcher = User::factory()->create(['role' => 'dispatcher']);
    $this->plateSeq = 8000;
});

function makeTruck(object $t, TruckType $type = TruckType::CDD, FleetStatus $status = FleetStatus::AVAILABLE): Truck
{
    $brand = Brand::firstOrCreate(['slug' => 'isuzu'], ['name' => 'Isuzu', 'country' => 'Japan', 'category' => 'other', 'is_active' => true]);
    $car = Car::firstOrCreate(
        ['slug' => 'isuzu-giga-fvr'],
        ['brand_id' => $brand->id, 'model' => 'Giga FVR', 'year_start' => 2022, 'body_type' => 'Truck', 'fuel_type' => 'diesel', 'price_idr' => 750000000, 'is_active' => true]
    );
    $t->plateSeq++;
    $plate = 'DA '.$t->plateSeq.' TA';
    $vehicle = app(AcquiresVehicle::class)->handle(
        user: $t->dispatcher, car: $car, plateNumber: $plate, color: 'White', vin: 'MHFVR34P0NK'.str_pad((string) $t->plateSeq, 6, '0', STR_PAD_LEFT)
    );

    return Truck::create([
        'vehicle_id' => $vehicle->id, 'plate_number' => $plate, 'type' => $type, 'payload_kg' => 5000, 'volume_dm3' => 14000,
        'required_license' => $type->requiredLicense(), 'service_interval_m' => 10_000_000, 'odometer_m' => 1_000_000,
        'status' => $status, 'current_location_id' => $t->hubA->id,
    ]);
}

function makeDriver(object $t, array $overrides = []): Driver
{
    static $n = 0;
    $n++;

    return Driver::create(array_merge([
        'user_id' => User::factory()->create(['role' => 'driver'])->id,
        'driver_number' => 'DRV-T-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
        'license_class' => 'SIM B2 Umum', 'license_expiry' => now()->addYear(), 'home_hub_id' => $t->hubA->id, 'status' => 'available',
    ], $overrides));
}

function makeTrip(object $t, int $startInHours = 2, int $durationHours = 2, array $overrides = []): Schedule
{
    static $n = 0;
    $n++;

    return Schedule::create(array_merge([
        'schedule_number' => 'TRP-T-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
        'mode' => TransportMode::ROAD, 'origin_location_id' => $t->hubA->id, 'destination_location_id' => $t->hubB->id,
        'etd' => now()->addHours($startInHours), 'eta' => now()->addHours($startInHours + $durationHours),
        'cutoff_at' => now()->addHour(), 'status' => ScheduleStatus::Scheduled,
        'cap_weight_kg' => '5000.000', 'cap_volume_dm3' => 14000,
    ], $overrides));
}

function dispatchAction(): AssignScheduleResourcesAction
{
    return app(AssignScheduleResourcesAction::class);
}

test('dispatcher assigns truck and driver to a road schedule', function () {
    $truck = makeTruck($this);
    $driver = makeDriver($this);
    $trip = makeTrip($this);

    $assignment = dispatchAction()->execute($trip, $truck, $driver, $this->dispatcher);

    expect($assignment->status)->toBe('active')
        ->and($assignment->trip_minutes)->toBe(120)
        ->and($assignment->assigned_by)->toBe($this->dispatcher->id)
        ->and($trip->fresh()->asset_id)->toBe($truck->id)
        ->and($trip->fresh()->driver_id)->toBe($driver->id)
        ->and($truck->fresh()->status)->toBe(FleetStatus::ASSIGNED);
});

test('assignment is rejected when the driver license is expired on the trip date', function () {
    $driver = makeDriver($this, ['license_expiry' => now()->subDay()]);

    expect(fn () => dispatchAction()->execute(makeTrip($this), makeTruck($this), $driver, $this->dispatcher))
        ->toThrow(DriverLicenseExpiredException::class);
});

test('assignment is rejected when the license class does not match the truck', function () {
    $truck = makeTruck($this, TruckType::TRONTON); // SIM B2 Umum
    $driver = makeDriver($this, ['license_class' => 'SIM B1 Umum']);

    expect(fn () => dispatchAction()->execute(makeTrip($this), $truck, $driver, $this->dispatcher))
        ->toThrow(IncompatibleLicenseException::class);
    expect($truck->fresh()->status)->toBe(FleetStatus::AVAILABLE);
});

test('assignment is rejected when a single trip exceeds the continuous driving limit', function () {
    expect(fn () => dispatchAction()->execute(makeTrip($this, 2, 5), makeTruck($this), makeDriver($this), $this->dispatcher))
        ->toThrow(DrivingHoursLimitExceededException::class);
});

test('daily driving limit counts other trips already assigned on the same day', function () {
    $this->travelTo(now()->startOfDay()->addHours(6)); // seluruh trip jatuh pada hari yang sama
    $driver = makeDriver($this);

    // 4 jam (240 menit) + 4 jam lain = 480 menit: lolos. Trip ke-3 berapapun melebihi 8 jam.
    $a = makeTrip($this, 1, 4);
    $b = makeTrip($this, 6, 4);
    $c = makeTrip($this, 11, 1);
    dispatchAction()->execute($a, makeTruck($this), $driver, $this->dispatcher);
    dispatchAction()->execute($b, makeTruck($this), $driver, $this->dispatcher);

    expect(fn () => dispatchAction()->execute($c, makeTruck($this), $driver, $this->dispatcher))
        ->toThrow(DrivingHoursLimitExceededException::class);
});

test('truck under maintenance cannot be assigned', function () {
    $truck = makeTruck($this, TruckType::CDD, FleetStatus::MAINTENANCE);

    expect(fn () => dispatchAction()->execute(makeTrip($this), $truck, makeDriver($this), $this->dispatcher))
        ->toThrow(TruckNotAssignableException::class, 'perawatan');
});

test('truck already assigned to another schedule cannot be taken', function () {
    $truck = makeTruck($this);
    dispatchAction()->execute(makeTrip($this, 2, 2), $truck, makeDriver($this), $this->dispatcher);

    expect(fn () => dispatchAction()->execute(makeTrip($this, 30, 2), $truck, makeDriver($this), $this->dispatcher))
        ->toThrow(TruckNotAssignableException::class);
});

test('assignment is rejected when the vehicle passport hash chain is broken', function () {
    $truck = makeTruck($this);
    VehicleEvent::where('vehicle_id', $truck->vehicle_id)->firstOrFail()->forceFill(['hash' => str_repeat('a', 64)])->saveQuietly();

    expect(fn () => dispatchAction()->execute(makeTrip($this), $truck, makeDriver($this), $this->dispatcher))
        ->toThrow(InvalidVehiclePassportException::class);
    expect($truck->fresh()->status)->toBe(FleetStatus::AVAILABLE);
});

test('overlapping schedules for the same driver are rejected', function () {
    $driver = makeDriver($this);
    dispatchAction()->execute(makeTrip($this, 2, 2), makeTruck($this), $driver, $this->dispatcher);

    expect(fn () => dispatchAction()->execute(makeTrip($this, 3, 2), makeTruck($this), $driver, $this->dispatcher))
        ->toThrow(ScheduleConflictException::class);
});

test('suspended driver is rejected', function () {
    $driver = makeDriver($this, ['status' => 'suspended']);

    expect(fn () => dispatchAction()->execute(makeTrip($this), makeTruck($this), $driver, $this->dispatcher))
        ->toThrow(DriverNotAssignableException::class);
});

test('only road schedules in scheduled or loading status can be assigned', function () {
    $sea = makeTrip($this, 2, 2, ['mode' => TransportMode::SEA]);
    $departed = makeTrip($this, 2, 2, ['status' => ScheduleStatus::Departed]);

    expect(fn () => dispatchAction()->execute($sea, makeTruck($this), makeDriver($this), $this->dispatcher))
        ->toThrow(InvalidDispatchException::class);
    expect(fn () => dispatchAction()->execute($departed, makeTruck($this), makeDriver($this), $this->dispatcher))
        ->toThrow(InvalidDispatchException::class);
});

test('releasing an assignment frees the truck and clears the schedule', function () {
    $truck = makeTruck($this);
    $trip = makeTrip($this);
    dispatchAction()->execute($trip, $truck, makeDriver($this), $this->dispatcher);

    app(ReleaseScheduleResourcesAction::class)->execute($trip, 'Driver sakit');

    expect($truck->fresh()->status)->toBe(FleetStatus::AVAILABLE)
        ->and($trip->fresh()->asset_id)->toBeNull()
        ->and($trip->fresh()->driver_id)->toBeNull()
        ->and(DispatchAssignment::first()->status)->toBe('released')
        ->and(DispatchAssignment::first()->release_reason)->toBe('Driver sakit');
});

test('reassigning a schedule releases the previous truck', function () {
    $oldTruck = makeTruck($this);
    $newTruck = makeTruck($this);
    $trip = makeTrip($this);
    dispatchAction()->execute($trip, $oldTruck, makeDriver($this), $this->dispatcher);

    dispatchAction()->execute($trip->fresh(), $newTruck, makeDriver($this), $this->dispatcher);

    expect($oldTruck->fresh()->status)->toBe(FleetStatus::AVAILABLE)
        ->and($newTruck->fresh()->status)->toBe(FleetStatus::ASSIGNED)
        ->and($trip->fresh()->asset_id)->toBe($newTruck->id)
        ->and(DispatchAssignment::where('status', 'active')->count())->toBe(1)
        ->and(DispatchAssignment::where('status', 'released')->count())->toBe(1);
});

function makeBookedShipment(object $t, ShipmentStatus $status = ShipmentStatus::Booked): Shipment
{
    return Shipment::create([
        'tracking_number' => TrackingNumber::generate(), 'shipper_id' => User::factory()->create(['role' => 'shipper'])->id,
        'consignee_name' => 'Budi Santoso', 'consignee_phone' => '081234567890',
        'consignee_address' => ['street' => 'Jl. Ahmad Yani 1', 'city' => 'Banjarbaru'],
        'origin_location_id' => $t->hubA->id, 'destination_location_id' => $t->hubB->id,
        'service_level' => ServiceLevel::Regular, 'mode' => TransportMode::ROAD, 'payment_terms' => PaymentTerms::Prepaid,
        'status' => $status, 'total_chargeable_weight_g' => 1000, 'total_amount_idr' => 50000, 'booked_at' => now(),
    ]);
}

test('shipment can be assigned to a driver and the event is recorded in the custody chain', function () {
    $driver = makeDriver($this);
    $shipment = makeBookedShipment($this);

    app(AssignShipmentToDriverAction::class)->execute($shipment, $driver, $this->dispatcher);

    $event = $shipment->trackingEvents()->first();
    expect($shipment->fresh()->driver_id)->toBe($driver->id)
        ->and($event->event_type)->toBe('DRIVER_ASSIGNED')
        ->and($event->payload['stage'])->toBe('pickup');
});

test('delivered shipments and suspended or expired drivers cannot be assigned', function () {
    $action = app(AssignShipmentToDriverAction::class);

    expect(fn () => $action->execute(makeBookedShipment($this, ShipmentStatus::Delivered), makeDriver($this), $this->dispatcher))
        ->toThrow(InvalidDispatchException::class);
    expect(fn () => $action->execute(makeBookedShipment($this), makeDriver($this, ['status' => 'suspended']), $this->dispatcher))
        ->toThrow(DriverNotAssignableException::class);
    expect(fn () => $action->execute(makeBookedShipment($this), makeDriver($this, ['license_expiry' => now()->subDay()]), $this->dispatcher))
        ->toThrow(DriverLicenseExpiredException::class);
});

test('dispatch board is limited to dispatcher and logistics admins', function () {
    foreach (['dispatcher', 'logistics_admin', 'admin'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))->get(route('logistics.dispatch.index'))->assertOk();
    }

    foreach (['shipper', 'hub_operator', 'driver', 'customer'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))->get(route('logistics.dispatch.index'))->assertForbidden();
    }

    auth()->logout();
    $this->get(route('logistics.dispatch.index'))->assertRedirect();
});

test('dispatcher assigns through the board and sees validation errors as flash messages', function () {
    $trip = makeTrip($this);
    $truck = makeTruck($this);
    $good = makeDriver($this);
    $expired = makeDriver($this, ['license_expiry' => now()->subDay()]);

    $this->actingAs($this->dispatcher)->get(route('logistics.dispatch.index'))->assertOk()->assertSee($trip->schedule_number);

    $this->actingAs($this->dispatcher)
        ->post(route('logistics.dispatch.assign'), ['schedule_id' => $trip->id, 'truck_id' => $truck->id, 'driver_id' => $expired->id])
        ->assertSessionHas('error');
    expect($trip->fresh()->asset_id)->toBeNull();

    $this->actingAs($this->dispatcher)
        ->post(route('logistics.dispatch.assign'), ['schedule_id' => $trip->id, 'truck_id' => $truck->id, 'driver_id' => $good->id])
        ->assertSessionHas('success');
    expect($trip->fresh()->driver_id)->toBe($good->id);

    $this->actingAs($this->dispatcher)->delete(route('logistics.dispatch.release', $trip))->assertSessionHas('success');
    expect($trip->fresh()->asset_id)->toBeNull();
});

test('non dispatcher roles cannot post dispatch actions', function () {
    $trip = makeTrip($this);

    $this->actingAs(User::factory()->create(['role' => 'shipper']))
        ->post(route('logistics.dispatch.assign'), ['schedule_id' => $trip->id, 'truck_id' => 1, 'driver_id' => 1])
        ->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'hub_operator']))
        ->delete(route('logistics.dispatch.release', $trip))
        ->assertForbidden();
});
