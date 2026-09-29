<?php

use App\Models\Brand;
use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================
// HELPERS
// ============================================================

function createAutoDexCustomer(): User
{
    return User::create([
        'name' => 'Dex Customer',
        'email' => 'dexcustomer@test.com',
        'phone' => '08200',
        'role' => 'customer',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);
}

function createBrandAndCar(): array
{
    $brand = Brand::create([
        'name' => 'Toyota',
        'slug' => 'toyota',
        'country' => 'Japan',
        'category' => 'jdm',
    ]);

    $car = Car::create([
        'brand_id' => $brand->id,
        'model' => 'Supra',
        'slug' => 'toyota-supra-2019',
        'year_start' => 2019,
        'body_type' => 'Coupe',
        'fuel_type' => 'gasoline',
        'engine' => '3.0L Turbo I6',
        'horsepower' => 382,
        'torque_nm' => 500,
        'transmission' => 'AT 8-Speed',
        'drivetrain' => 'RWD',
        'price_idr' => 2100000000,
    ]);

    return [$brand, $car];
}

// ============================================================
// CATALOG
// ============================================================

test('autodex catalog page loads for authenticated user', function () {
    $user = createAutoDexCustomer();

    $response = $this->actingAs($user)->get(route('autodex.index'));
    $response->assertOk();
});

test('autodex catalog returns JSON for AJAX requests', function () {
    $user = createAutoDexCustomer();
    [$brand, $car] = createBrandAndCar();

    $response = $this->actingAs($user)
        ->get(route('autodex.index', ['search' => 'Supra']), [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ]);

    $response->assertOk();
    $response->assertJsonStructure(['html', 'pagination']);
});

test('autodex live search filters by brand name', function () {
    $user = createAutoDexCustomer();
    [$brand, $car] = createBrandAndCar();

    // Also create a Honda
    $honda = Brand::create(['name' => 'Honda', 'slug' => 'honda', 'country' => 'Japan', 'category' => 'jdm']);
    Car::create([
        'brand_id' => $honda->id, 'model' => 'Civic', 'slug' => 'honda-civic-2022',
        'year_start' => 2022, 'fuel_type' => 'gasoline',
    ]);

    $response = $this->actingAs($user)
        ->get(route('autodex.index', ['search' => 'Toyota']), [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ]);

    $response->assertOk();
    $data = $response->json();
    expect($data['html'])->toContain('Supra');
    expect($data['html'])->not->toContain('Civic');
});

test('autodex catalog filters by category', function () {
    $user = createAutoDexCustomer();
    [$brand, $car] = createBrandAndCar(); // JDM

    $bmw = Brand::create(['name' => 'BMW', 'slug' => 'bmw', 'country' => 'Germany', 'category' => 'euro']);
    Car::create([
        'brand_id' => $bmw->id, 'model' => 'M3', 'slug' => 'bmw-m3-2021',
        'year_start' => 2021, 'fuel_type' => 'gasoline',
    ]);

    $response = $this->actingAs($user)
        ->get(route('autodex.index', ['category' => 'euro']), [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ]);

    $response->assertOk();
    $data = $response->json();
    expect($data['html'])->toContain('M3');
    expect($data['html'])->not->toContain('Supra');
});

test('autodex car detail page loads', function () {
    $user = createAutoDexCustomer();
    [$brand, $car] = createBrandAndCar();

    $response = $this->actingAs($user)->get(route('autodex.show', $car->slug));
    $response->assertOk();
    $response->assertSee('Supra');
});

// ============================================================
// GARAGE & WISHLIST
// ============================================================

test('user can add car to garage', function () {
    $user = createAutoDexCustomer();
    [$brand, $car] = createBrandAndCar();

    $response = $this->actingAs($user)->post(route('autodex.garage.toggle', $car));
    $response->assertRedirect();

    expect($user->hasInGarage($car->id))->toBeTrue();
});

test('user can remove car from garage (toggle)', function () {
    $user = createAutoDexCustomer();
    [$brand, $car] = createBrandAndCar();

    // Add first
    $user->garageCars()->attach($car->id);
    expect($user->hasInGarage($car->id))->toBeTrue();

    // Toggle to remove
    $response = $this->actingAs($user)->post(route('autodex.garage.toggle', $car));
    $response->assertRedirect();

    expect($user->hasInGarage($car->id))->toBeFalse();
});

test('user can add car to wishlist', function () {
    $user = createAutoDexCustomer();
    [$brand, $car] = createBrandAndCar();

    $response = $this->actingAs($user)->post(route('autodex.wishlist.toggle', $car));
    $response->assertRedirect();

    expect($user->hasInWishlist($car->id))->toBeTrue();
});

test('adding car to garage removes it from wishlist', function () {
    $user = createAutoDexCustomer();
    [$brand, $car] = createBrandAndCar();

    // First add to wishlist
    $user->wishlistCars()->attach($car->id);
    expect($user->hasInWishlist($car->id))->toBeTrue();

    // Then add to garage (should auto-remove from wishlist)
    $response = $this->actingAs($user)->post(route('autodex.garage.toggle', $car));
    $response->assertRedirect();

    expect($user->hasInGarage($car->id))->toBeTrue();
    expect($user->hasInWishlist($car->id))->toBeFalse();
});

test('cannot wishlist a car already in garage', function () {
    $user = createAutoDexCustomer();
    [$brand, $car] = createBrandAndCar();

    // Add to garage
    $user->garageCars()->attach($car->id);

    // Try to wishlist
    $response = $this->actingAs($user)->post(route('autodex.wishlist.toggle', $car));
    $response->assertRedirect();

    // Should NOT be in wishlist
    expect($user->hasInWishlist($car->id))->toBeFalse();
});

test('garage page loads and shows owned cars', function () {
    $user = createAutoDexCustomer();
    [$brand, $car] = createBrandAndCar();

    $user->garageCars()->attach($car->id);

    $response = $this->actingAs($user)->get(route('autodex.garage.index'));
    $response->assertOk();
    $response->assertSee('Supra');
});
