<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AutoDex\Domain\Models\Brand;
use Modules\AutoDex\Domain\Models\Car;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Service;
use Modules\Core\Application\Actions\AcquireVehicleAction;

uses(RefreshDatabase::class);

test('customer can create a booking linked to a vehicle from their garage', function () {
    $customer = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => bcrypt('password'),
        'role' => 'customer',
    ]);

    $service = Service::create([
        'name' => 'Ganti Oli',
        'price' => 150000,
        'is_active' => true,
    ]);

    $brand = Brand::create([
        'name' => 'Toyota',
        'slug' => 'toyota',
        'country' => 'Japan',
        'category' => 'jdm',
    ]);

    $car = Car::create([
        'brand_id' => $brand->id,
        'model' => 'GR Yaris',
        'slug' => 'toyota-gr-yaris-2022',
        'year_start' => 2022,
        'body_type' => 'Hatchback',
        'fuel_type' => 'gasoline',
        'is_active' => true,
    ]);

    $vehicle = app(AcquireVehicleAction::class)->handle(
        user: $customer,
        car: $car,
        plateNumber: 'B 8888 GR',
        color: 'Black',
    );

    $response = $this->actingAs($customer)->post(route('bookings.store'), [
        'service_id' => $service->id,
        'vehicle_id' => $vehicle->id,
        'plate_number' => $vehicle->plate_number,
        'vehicle_brand' => 'Toyota',
        'vehicle_model' => 'GR Yaris',
        'vehicle_year' => 2022,
        'complaint' => 'Servis berkala 10.000km',
        'booking_date' => now()->addDay()->format('Y-m-d'),
        'booking_time' => '10:00',
    ]);

    $response->assertRedirect(route('dashboard'));

    $booking = Booking::latest()->first();
    expect($booking)->not->toBeNull();
    expect($booking->vehicle_id)->toBe($vehicle->id);
    expect($booking->vehicle->id)->toBe($vehicle->id);
    expect($booking->vehicle->car->model)->toBe('GR Yaris');
});
