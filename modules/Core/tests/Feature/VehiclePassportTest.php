<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\AutoDex\Domain\Models\Brand;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Core\Application\Actions\AcquireVehicleAction;
use Modules\Core\Application\Actions\RecordVehicleEventAction;
use Modules\Core\Application\Actions\VerifyPassportAction;
use Modules\Core\Domain\Enums\VehicleEventType;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Core\Domain\Models\VehicleEvent;

uses(RefreshDatabase::class);

function passportOwner(string $email = 'passport-owner@example.com'): User
{
    return User::create([
        'name' => 'Passport Owner',
        'email' => $email,
        'password' => bcrypt('password'),
        'role' => 'customer',
    ]);
}

function passportCar(): Car
{
    $brand = Brand::create([
        'name' => 'Toyota',
        'slug' => 'toyota-passport',
        'country' => 'Japan',
        'category' => 'jdm',
    ]);

    return Car::create([
        'brand_id' => $brand->id,
        'model' => 'Supra MK4',
        'slug' => 'toyota-supra-mk4-passport',
        'year_start' => 1997,
        'body_type' => 'Coupe',
        'fuel_type' => 'gasoline',
        'is_active' => true,
    ]);
}

function acquirePassportVehicle(User $owner): Vehicle
{
    return app(AcquireVehicleAction::class)->handle(
        user: $owner,
        car: passportCar(),
        plateNumber: 'B 4 SUPRA',
        color: 'Renaissance Red',
        vin: 'JT2DE82A7V0000001',
        odometerKm: 90000,
        acquiredViaType: 'manual',
    );
}

test('rantai hash paspor valid setelah beberapa event tercatat', function () {
    $owner = passportOwner();
    $vehicle = acquirePassportVehicle($owner);

    $recorder = app(RecordVehicleEventAction::class);
    $recorder->execute($vehicle, VehicleEventType::SERVICE_COMPLETED, ['booking' => 'BK-001', 'total' => 750000]);
    $recorder->execute($vehicle, VehicleEventType::ODOMETER_UPDATED, ['odometer_km' => 92000]);

    $events = VehicleEvent::where('vehicle_id', $vehicle->id)->orderBy('sequence')->get();

    expect($events)->toHaveCount(3);
    expect($events[0]->prev_hash)->toBe(str_repeat('0', 64));
    expect($events[1]->prev_hash)->toBe($events[0]->hash);
    expect($events[2]->prev_hash)->toBe($events[1]->hash);

    $result = app(VerifyPassportAction::class)->execute($vehicle);

    expect($result['is_valid'])->toBeTrue();
    expect($result['event_count'])->toBe(3);
    expect($result['broken_at_sequence'])->toBeNull();
});

test('manipulasi payload langsung di database terdeteksi pada sequence yang dirusak', function () {
    $owner = passportOwner();
    $vehicle = acquirePassportVehicle($owner);

    $recorder = app(RecordVehicleEventAction::class);
    $recorder->execute($vehicle, VehicleEventType::SERVICE_COMPLETED, ['booking' => 'BK-002', 'total' => 500000]);
    $recorder->execute($vehicle, VehicleEventType::ODOMETER_UPDATED, ['odometer_km' => 95000]);

    // Pemalsuan: turunkan odometer langsung lewat query builder (melewati model)
    $tampered = VehicleEvent::where('vehicle_id', $vehicle->id)->where('sequence', 3)->firstOrFail();
    DB::table('core_vehicle_events')
        ->where('id', $tampered->id)
        ->update(['payload' => json_encode(['odometer_km' => 10000])]);

    $result = app(VerifyPassportAction::class)->execute($vehicle->fresh());

    expect($result['is_valid'])->toBeFalse();
    expect($result['broken_at_sequence'])->toBe(3);
});

test('model vehicle event menolak update dan delete karena append-only', function () {
    $owner = passportOwner();
    $vehicle = acquirePassportVehicle($owner);

    $event = VehicleEvent::where('vehicle_id', $vehicle->id)->firstOrFail();

    expect(fn () => $event->update(['payload' => ['dipalsukan' => true]]))
        ->toThrow(RuntimeException::class);

    expect(fn () => $event->delete())
        ->toThrow(RuntimeException::class);
});

test('command core verify-passports berjalan bersih untuk rantai yang valid', function () {
    $owner = passportOwner();
    $vehicle = acquirePassportVehicle($owner);

    app(RecordVehicleEventAction::class)
        ->execute($vehicle, VehicleEventType::SERVICE_COMPLETED, ['booking' => 'BK-003']);

    $this->artisan('core:verify-passports')->assertSuccessful();
});

test('halaman paspor publik hanya dapat diakses dengan signed url', function () {
    $owner = passportOwner();
    $vehicle = acquirePassportVehicle($owner);

    $this->get('/passport/'.$vehicle->uuid)->assertForbidden();

    $this->get($vehicle->getPassportUrl())
        ->assertOk()
        ->assertSee('Supra MK4');
});
