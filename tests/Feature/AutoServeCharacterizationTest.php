<?php

use App\Models\Booking;
use App\Models\Service;
use App\Models\Sparepart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================
// HELPERS
// ============================================================

function createCustomer(): User
{
    return User::create([
        'name' => 'Test Customer',
        'email' => 'testcustomer@test.com',
        'phone' => '08123',
        'role' => 'customer',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);
}

function createMechanic(): User
{
    return User::create([
        'name' => 'Test Mekanik',
        'email' => 'testmek@test.com',
        'phone' => '08124',
        'role' => 'mekanik',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);
}

function createAdmin(): User
{
    return User::create([
        'name' => 'Test Admin',
        'email' => 'testadmin@test.com',
        'phone' => '08125',
        'role' => 'admin',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);
}

function createService(): Service
{
    return Service::create([
        'name' => 'Tune Up',
        'description' => 'Full tune up',
        'price' => 350000,
    ]);
}

function createSparepart(int $stock = 10, float $price = 85000): Sparepart
{
    static $counter = 0;
    $counter++;

    return Sparepart::create([
        'name' => "Sparepart {$counter}",
        'code' => "SP-TEST-{$counter}",
        'stock' => $stock,
        'price' => $price,
        'unit' => 'pcs',
    ]);
}

// ============================================================
// BOOKING FULL FLOW
// ============================================================

test('customer can create a booking', function () {
    $customer = createCustomer();
    $service = createService();

    $response = $this->actingAs($customer)->post(route('bookings.store'), [
        'service_id' => $service->id,
        'plate_number' => 'B 1234 XYZ',
        'vehicle_brand' => 'Toyota',
        'vehicle_model' => 'Avanza',
        'vehicle_year' => 2022,
        'complaint' => 'Mesin brebet',
        'booking_date' => now()->addDay()->format('Y-m-d'),
    ]);

    $response->assertRedirect(route('dashboard'));
    expect(Booking::count())->toBe(1);

    $booking = Booking::first();
    expect($booking->status)->toBe('pending');
    expect($booking->customer_id)->toBe($customer->id);
    expect($booking->service_cost)->toEqual(350000);
});

test('admin can assign mechanic to booking', function () {
    $customer = createCustomer();
    $admin = createAdmin();
    $mechanic = createMechanic();
    $service = createService();

    $booking = Booking::create([
        'booking_code' => 'AUTO-TEST01',
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'plate_number' => 'B 1234 XYZ',
        'vehicle_brand' => 'Toyota',
        'complaint' => 'Test',
        'booking_date' => now()->addDay(),
        'status' => 'pending',
        'service_cost' => $service->price,
    ]);

    $response = $this->actingAs($admin)->patch(route('bookings.assign', $booking), [
        'mechanic_id' => $mechanic->id,
    ]);

    $response->assertRedirect();
    $booking->refresh();
    expect($booking->status)->toBe('confirmed');
    expect($booking->mechanic_id)->toBe($mechanic->id);
});

test('mechanic can update status to in_progress', function () {
    $customer = createCustomer();
    $mechanic = createMechanic();
    $service = createService();

    $booking = Booking::create([
        'booking_code' => 'AUTO-TEST02',
        'customer_id' => $customer->id,
        'mechanic_id' => $mechanic->id,
        'service_id' => $service->id,
        'plate_number' => 'B 5678 ABC',
        'vehicle_brand' => 'Honda',
        'complaint' => 'AC dingin',
        'booking_date' => now()->addDay(),
        'status' => 'confirmed',
        'service_cost' => $service->price,
    ]);

    $response = $this->actingAs($mechanic)->patch(route('bookings.updateStatus', $booking), [
        'status' => 'in_progress',
    ]);

    $response->assertRedirect();
    $booking->refresh();
    expect($booking->status)->toBe('in_progress');
});

test('mechanic can add sparepart during in_progress', function () {
    $customer = createCustomer();
    $mechanic = createMechanic();
    $service = createService();
    $sparepart = createSparepart(stock: 10, price: 85000);

    $booking = Booking::create([
        'booking_code' => 'AUTO-TEST03',
        'customer_id' => $customer->id,
        'mechanic_id' => $mechanic->id,
        'service_id' => $service->id,
        'plate_number' => 'B 9999 ZZZ',
        'vehicle_brand' => 'Suzuki',
        'complaint' => 'Ganti oli',
        'booking_date' => now()->addDay(),
        'status' => 'in_progress',
        'service_cost' => $service->price,
    ]);

    $response = $this->actingAs($mechanic)->post(route('bookings.addSparepart', $booking), [
        'sparepart_id' => $sparepart->id,
        'quantity' => 2,
    ]);

    $response->assertRedirect();
    $booking->refresh();
    expect($booking->spareparts)->toHaveCount(1);
    expect($booking->sparepart_cost)->toEqual(170000.00); // 85000 * 2
    expect($booking->grand_total)->toEqual(520000.00); // 350000 + 170000
});

test('completing booking deducts stock and calculates grand total', function () {
    $customer = createCustomer();
    $mechanic = createMechanic();
    $service = createService();
    $sparepart = createSparepart(stock: 10, price: 85000);

    $booking = Booking::create([
        'booking_code' => 'AUTO-TEST04',
        'customer_id' => $customer->id,
        'mechanic_id' => $mechanic->id,
        'service_id' => $service->id,
        'plate_number' => 'B 1111 AAA',
        'vehicle_brand' => 'Daihatsu',
        'complaint' => 'Boros bensin',
        'booking_date' => now()->addDay(),
        'status' => 'in_progress',
        'service_cost' => $service->price,
    ]);

    // Attach sparepart
    $booking->spareparts()->attach($sparepart->id, [
        'quantity' => 3,
        'unit_price' => 85000,
        'subtotal' => 255000,
    ]);

    $booking->update([
        'sparepart_cost' => 255000,
        'grand_total' => $service->price + 255000,
    ]);

    // Complete the booking
    $response = $this->actingAs($mechanic)->patch(route('bookings.updateStatus', $booking), [
        'status' => 'completed',
    ]);

    $response->assertRedirect();

    $booking->refresh();
    expect($booking->status)->toBe('completed');
    expect($booking->grand_total)->toEqual(605000.00); // 350000 + 255000

    // Stock should be deducted
    $sparepart->refresh();
    expect($sparepart->stock)->toBe(7); // 10 - 3
});

test('invoice is accessible for completed booking', function () {
    $customer = createCustomer();
    $mechanic = createMechanic();
    $service = createService();

    $booking = Booking::create([
        'booking_code' => 'AUTO-TEST05',
        'customer_id' => $customer->id,
        'mechanic_id' => $mechanic->id,
        'service_id' => $service->id,
        'plate_number' => 'B 2222 BBB',
        'vehicle_brand' => 'Nissan',
        'complaint' => 'Rem bunyi',
        'booking_date' => now()->addDay(),
        'status' => 'completed',
        'service_cost' => $service->price,
        'grand_total' => $service->price,
    ]);

    $response = $this->actingAs($customer)->get(route('bookings.invoice', $booking));
    $response->assertOk();
    $response->assertViewIs('bookings.invoice');

    $booking->refresh();
    expect($booking->status)->toBe('invoiced');
});

test('customer cannot see other customers bookings', function () {
    $customer1 = createCustomer();
    $customer2 = User::create([
        'name' => 'Other Customer',
        'email' => 'other@test.com',
        'role' => 'customer',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);
    $service = createService();

    $booking = Booking::create([
        'booking_code' => 'AUTO-TEST06',
        'customer_id' => $customer1->id,
        'service_id' => $service->id,
        'plate_number' => 'B 3333 CCC',
        'vehicle_brand' => 'Mazda',
        'complaint' => 'Test',
        'booking_date' => now()->addDay(),
        'status' => 'pending',
        'service_cost' => $service->price,
    ]);

    $response = $this->actingAs($customer2)->get(route('bookings.show', $booking));
    $response->assertForbidden();
});

test('customer cannot access admin routes', function () {
    $customer = createCustomer();

    $response = $this->actingAs($customer)->get(route('services.index'));
    $response->assertForbidden();

    $response = $this->actingAs($customer)->get(route('spareparts.index'));
    $response->assertForbidden();
});
