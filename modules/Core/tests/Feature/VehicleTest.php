<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\AutoDex\Domain\Models\Brand;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Core\Application\Actions\AcquireVehicleAction;
use Modules\Core\Domain\Events\VehicleAcquired;
use Modules\Core\Domain\Models\Vehicle;

uses(RefreshDatabase::class);

test('AcquireVehicleAction creates vehicle, removes car from wishlist, and dispatches VehicleAcquired event', function () {
    Event::fake([VehicleAcquired::class]);

    $user = User::create([
        'name' => 'Car Owner',
        'email' => 'owner@example.com',
        'password' => bcrypt('password'),
        'role' => 'customer',
    ]);

    $brand = Brand::create([
        'name' => 'Honda',
        'slug' => 'honda',
        'country' => 'Japan',
        'category' => 'jdm',
    ]);

    $car = Car::create([
        'brand_id' => $brand->id,
        'model' => 'Civic Type R',
        'slug' => 'honda-civic-type-r-2023',
        'year_start' => 2023,
        'body_type' => 'Hatchback',
        'fuel_type' => 'gasoline',
        'is_active' => true,
    ]);

    // Put in wishlist first
    $user->wishlistCars()->attach($car->id);
    expect($user->hasInWishlist($car->id))->toBeTrue();

    // Acquire vehicle
    $action = app(AcquireVehicleAction::class);
    $vehicle = $action->handle(
        user: $user,
        car: $car,
        plateNumber: 'B 1999 CTR',
        color: 'Championship White',
        vin: 'VIN1234567890',
        odometerKm: 1500,
        acquiredViaType: 'manual',
    );

    expect($vehicle)->toBeInstanceOf(Vehicle::class);
    expect($vehicle->plate_number)->toBe('B 1999 CTR');
    expect($vehicle->color)->toBe('Championship White');
    expect($vehicle->status)->toBe('active');
    expect($vehicle->uuid)->not->toBeEmpty();

    // Wishlist should now be removed
    expect($user->fresh()->hasInWishlist($car->id))->toBeFalse();
    expect($user->fresh()->hasInGarage($car->id))->toBeTrue();

    Event::assertDispatched(VehicleAcquired::class, function (VehicleAcquired $event) use ($vehicle, $user) {
        return $event->vehicle->id === $vehicle->id && $event->actorId === $user->id;
    });
});
