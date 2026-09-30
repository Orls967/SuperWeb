<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Car;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Contracts\AcquiresVehicle;
use Modules\Logistics\Application\Actions\ReleaseCapacityAction;
use Modules\Logistics\Application\Actions\ReserveCapacityAction;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Enums\TruckType;
use Modules\Logistics\Domain\Exceptions\CapacityCutoffExceededException;
use Modules\Logistics\Domain\Exceptions\CapacityExceededException;
use Modules\Logistics\Domain\Exceptions\ScheduleConflictException;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Truck;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->origin = Location::create([
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

    $this->destination = Location::create([
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

    $this->schedule = Schedule::create([
        'schedule_number' => 'TRP-RES-001',
        'mode' => TransportMode::ROAD,
        'origin_location_id' => $this->origin->id,
        'destination_location_id' => $this->destination->id,
        'etd' => now()->addHours(6),
        'eta' => now()->addHours(8),
        'cutoff_at' => now()->addHours(4),
        'status' => ScheduleStatus::Scheduled,
        'cap_weight_kg' => '3000.000',
        'cap_volume_dm3' => 10000,
        'cap_teu' => 2,
        'cap_uld_positions' => 0,
        'used_weight_kg' => '0.000',
        'used_volume_dm3' => 0,
        'used_teu' => 0,
        'used_uld_positions' => 0,
    ]);
});

test('reserve capacity action successfully allocates capacity and increments used counters', function () {
    $action = app(ReserveCapacityAction::class);

    $reservation = $action->execute(
        scheduleId: $this->schedule->id,
        weightKg: '500.500',
        volumeDm3: 2000,
        teu: 1,
        idempotencyKey: 'res-idemp-001'
    );

    expect($reservation->status)->toBe('active')
        ->and($reservation->allocated_weight_kg)->toBe('500.500')
        ->and($reservation->allocated_volume_dm3)->toBe(2000)
        ->and($reservation->allocated_teu)->toBe(1);

    $this->schedule->refresh();
    expect($this->schedule->used_weight_kg)->toBe('500.500')
        ->and($this->schedule->used_volume_dm3)->toBe(2000)
        ->and($this->schedule->used_teu)->toBe(1);
});

test('reserve capacity action rejects overbooking when weight or volume exceeds capacity', function () {
    $action = app(ReserveCapacityAction::class);

    // Exceed weight (3001 > 3000)
    expect(fn () => $action->execute(
        scheduleId: $this->schedule->id,
        weightKg: '3001.000',
        volumeDm3: 1000,
        idempotencyKey: 'res-over-001'
    ))->toThrow(CapacityExceededException::class);

    // Exceed volume (11000 > 10000)
    expect(fn () => $action->execute(
        scheduleId: $this->schedule->id,
        weightKg: '1000.000',
        volumeDm3: 11000,
        idempotencyKey: 'res-over-002'
    ))->toThrow(CapacityExceededException::class);

    // Exceed TEU (3 > 2)
    expect(fn () => $action->execute(
        scheduleId: $this->schedule->id,
        weightKg: '500.000',
        volumeDm3: 1000,
        teu: 3,
        idempotencyKey: 'res-over-003'
    ))->toThrow(CapacityExceededException::class);

    // Ensure schedule counters remain untouched
    $this->schedule->refresh();
    expect(BigDecimal::of((string) $this->schedule->used_weight_kg)->isZero())->toBeTrue()
        ->and($this->schedule->used_volume_dm3)->toBe(0);
});

test('reserve capacity action rejects booking after cutoff time has passed', function () {
    $this->schedule->update(['cutoff_at' => now()->subMinute()]);
    $action = app(ReserveCapacityAction::class);

    expect(fn () => $action->execute(
        scheduleId: $this->schedule->id,
        weightKg: '100.000',
        volumeDm3: 500,
        idempotencyKey: 'res-cutoff-001'
    ))->toThrow(CapacityCutoffExceededException::class);
});

test('reserve capacity action with same idempotency key does not duplicate deduction', function () {
    $action = app(ReserveCapacityAction::class);

    $first = $action->execute(
        scheduleId: $this->schedule->id,
        weightKg: '1000.000',
        volumeDm3: 2000,
        idempotencyKey: 'same-key-123'
    );

    $second = $action->execute(
        scheduleId: $this->schedule->id,
        weightKg: '1000.000',
        volumeDm3: 2000,
        idempotencyKey: 'same-key-123'
    );

    expect($second->id)->toBe($first->id);

    $this->schedule->refresh();
    // Only allocated once (1000 kg, not 2000 kg)
    expect($this->schedule->used_weight_kg)->toBe('1000.000')
        ->and($this->schedule->used_volume_dm3)->toBe(2000);
});

test('reserve capacity action detects driver or asset overlapping schedule conflict', function () {
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
        plateNumber: 'DA 8111 TA',
        color: 'White',
        vin: 'MHFVR34P0NK811101'
    );
    $truck = Truck::create([
        'vehicle_id' => $vehicle->id,
        'plate_number' => $vehicle->plate_number,
        'type' => TruckType::CDD,
        'payload_kg' => 5000,
        'volume_dm3' => 14000,
        'required_license' => 'BII',
        'service_interval_m' => 10_000_000,
        'odometer_m' => 10_000_000,
        'status' => FleetStatus::AVAILABLE,
        'current_location_id' => $this->origin->id,
    ]);

    $driverUser = User::factory()->create();
    $driver = Driver::create([
        'driver_number' => 'DRV-BDJ-999',
        'user_id' => $driverUser->id,
        'license_class' => 'BII',
        'license_expiry' => now()->addYear(),
        'home_hub_id' => $this->origin->id,
        'is_active' => true,
    ]);

    // Schedule 1: 10:00 to 14:00
    Schedule::create([
        'schedule_number' => 'TRP-CONF-1',
        'mode' => TransportMode::ROAD,
        'asset_type' => Truck::class,
        'asset_id' => $truck->id,
        'driver_id' => $driver->id,
        'origin_location_id' => $this->origin->id,
        'destination_location_id' => $this->destination->id,
        'etd' => now()->addHours(10),
        'eta' => now()->addHours(14),
        'cutoff_at' => now()->addHours(8),
        'status' => ScheduleStatus::Scheduled,
        'cap_weight_kg' => '5000.000',
        'cap_volume_dm3' => 14000,
    ]);

    // Schedule 2 with same driver overlapping: 12:00 to 16:00
    $schedule2 = Schedule::create([
        'schedule_number' => 'TRP-CONF-2',
        'mode' => TransportMode::ROAD,
        'driver_id' => $driver->id,
        'origin_location_id' => $this->origin->id,
        'destination_location_id' => $this->destination->id,
        'etd' => now()->addHours(12),
        'eta' => now()->addHours(16),
        'cutoff_at' => now()->addHours(11),
        'status' => ScheduleStatus::Scheduled,
        'cap_weight_kg' => '5000.000',
        'cap_volume_dm3' => 14000,
    ]);

    $action = app(ReserveCapacityAction::class);

    expect(fn () => $action->execute(
        scheduleId: $schedule2->id,
        weightKg: '500.000',
        volumeDm3: 500,
        idempotencyKey: 'res-conf-001'
    ))->toThrow(ScheduleConflictException::class);
});

test('release capacity action releases capacity and decrements used counters', function () {
    $reserveAction = app(ReserveCapacityAction::class);
    $releaseAction = app(ReleaseCapacityAction::class);

    $reservation = $reserveAction->execute(
        scheduleId: $this->schedule->id,
        weightKg: '800.250',
        volumeDm3: 3000,
        teu: 1,
        idempotencyKey: 'res-rel-001'
    );

    $this->schedule->refresh();
    expect($this->schedule->used_weight_kg)->toBe('800.250')
        ->and($this->schedule->used_volume_dm3)->toBe(3000)
        ->and($this->schedule->used_teu)->toBe(1);

    // Release capacity
    $released = $releaseAction->execute($reservation);

    expect($released->isReleased())->toBeTrue();

    $this->schedule->refresh();
    expect(BigDecimal::of((string) $this->schedule->used_weight_kg)->isZero())->toBeTrue()
        ->and($this->schedule->used_volume_dm3)->toBe(0)
        ->and($this->schedule->used_teu)->toBe(0);

    // Releasing again is idempotent and does not go negative
    $releaseAction->execute($reservation);
    $this->schedule->refresh();
    expect(BigDecimal::of((string) $this->schedule->used_weight_kg)->isZero())->toBeTrue()
        ->and($this->schedule->used_volume_dm3)->toBe(0);
});

test('lgx:capacity-check verifies clean sync between active allocations and schedule counters', function () {
    $reserveAction = app(ReserveCapacityAction::class);

    $reserveAction->execute(
        scheduleId: $this->schedule->id,
        weightKg: '1200.000',
        volumeDm3: 4000,
        teu: 1,
        idempotencyKey: 'res-audit-001'
    );

    // 1. Audit check should succeed with 0 discrepancies
    $exitCode = Artisan::call('lgx:capacity-check');
    expect($exitCode)->toBe(0);

    // 2. Induce a discrepancy (used counters out of sync with active allocations)
    $this->schedule->update(['used_weight_kg' => '9999.000']); // Corrupted value

    $failExitCode = Artisan::call('lgx:capacity-check');
    expect($failExitCode)->toBe(1);
});
