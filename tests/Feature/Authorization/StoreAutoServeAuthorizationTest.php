<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Service;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Core\database\seeders\RbacSeeder;
use Modules\Store\Domain\Models\Order;
use Modules\Store\Domain\Models\Product;
use Tests\TestCase;

class StoreAutoServeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(BankingSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('store.cart.index'))->assertRedirect('/login');
        $this->get(route('store.checkout.index'))->assertRedirect('/login');
        $this->get(route('store.orders.index'))->assertRedirect('/login');
        $this->get(route('bookings.create'))->assertRedirect('/login');
        $this->get(route('autodex.garage.index'))->assertRedirect('/login');
    }

    public function test_auditor_is_strictly_forbidden_from_mutations(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);

        $product = Product::create([
            'uuid' => (string) Str::uuid(),
            'sku' => 'SKU-TEST-'.Str::random(5),
            'name' => 'Produk Uji',
            'slug' => 'produk-uji-'.Str::random(5),
            'price' => 100000,
            'cached_stock' => 10,
            'is_listed' => true,
        ]);

        $service = Service::create([
            'name' => 'Servis Ringan',
            'description' => 'Ganti Oli',
            'price' => 150000,
            'is_active' => true,
        ]);

        $booking = Booking::create([
            'booking_code' => 'SRV-TEST-001',
            'customer_id' => $auditor->id,
            'service_id' => $service->id,
            'plate_number' => 'B1234XYZ',
            'vehicle_brand' => 'Toyota',
            'complaint' => 'Bunyi rem',
            'booking_date' => now()->toDateString(),
            'status' => 'pending',
            'service_fee' => 150000,
            'total_cost' => 150000,
        ]);

        // Store mutations forbidden for auditor
        $this->actingAs($auditor)->post(route('store.cart.store'), ['product_id' => $product->id, 'quantity' => 1])->assertForbidden();
        $this->actingAs($auditor)->patch(route('store.cart.update', $product), ['quantity' => 2])->assertForbidden();
        $this->actingAs($auditor)->delete(route('store.cart.destroy', $product))->assertForbidden();
        $this->actingAs($auditor)->post(route('store.checkout.process'), [])->assertForbidden();

        // AutoServe mutations forbidden for auditor
        $this->actingAs($auditor)->post(route('bookings.store'), [
            'service_id' => $service->id,
            'plate_number' => 'B5678XYZ',
            'vehicle_brand' => 'Honda',
            'complaint' => 'Ganti oli',
            'booking_date' => now()->toDateString(),
        ])->assertForbidden();
        $this->actingAs($auditor)->post(route('bookings.pay', $booking), ['pin' => '123456'])->assertForbidden();

        // AutoDex mutations forbidden for auditor
        $this->actingAs($auditor)->post(route('autodex.garage.toggle', ['car' => 1]))->assertForbidden();
        $this->actingAs($auditor)->post(route('autodex.wishlist.toggle', ['car' => 1]))->assertForbidden();
    }

    public function test_unauthorized_role_receives_403(): void
    {
        $unauthorized = User::factory()->create(['role' => 'operator']);

        $this->actingAs($unauthorized)->get(route('store.cart.index'))->assertForbidden();
        $this->actingAs($unauthorized)->get(route('store.checkout.index'))->assertForbidden();
        $this->actingAs($unauthorized)->get(route('store.orders.index'))->assertForbidden();
        $this->actingAs($unauthorized)->get(route('bookings.create'))->assertForbidden();
        $this->actingAs($unauthorized)->get(route('autodex.garage.index'))->assertForbidden();
    }

    public function test_customer_can_access_own_features_and_auditor_can_read(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $auditor = User::factory()->create(['role' => 'auditor']);

        // Customer access
        $this->actingAs($customer)->get(route('store.cart.index'))->assertSuccessful();
        $this->actingAs($customer)->get(route('store.orders.index'))->assertSuccessful();
        $this->actingAs($customer)->get(route('bookings.create'))->assertSuccessful();
        $this->actingAs($customer)->get(route('autodex.index'))->assertSuccessful();

        // Auditor read access
        $this->actingAs($auditor)->get(route('store.cart.index'))->assertSuccessful();
        $this->actingAs($auditor)->get(route('store.orders.index'))->assertSuccessful();
        $this->actingAs($auditor)->get(route('bookings.create'))->assertSuccessful();
        $this->actingAs($auditor)->get(route('autodex.index'))->assertSuccessful();
    }

    public function test_ownership_policy_prevents_customer_from_accessing_other_customer_data(): void
    {
        $customerA = User::factory()->create(['role' => 'customer']);
        $customerB = User::factory()->create(['role' => 'customer']);

        $orderA = Order::create([
            'number' => 'ORD-TEST-AAA',
            'user_id' => $customerA->id,
            'status' => 'pending_payment',
            'total_amount' => 200000,
        ]);

        $service = Service::create([
            'name' => 'Servis Berkala',
            'description' => 'Tune up',
            'price' => 200000,
            'is_active' => true,
        ]);

        $bookingA = Booking::create([
            'booking_code' => 'SRV-TEST-AAA',
            'customer_id' => $customerA->id,
            'service_id' => $service->id,
            'plate_number' => 'B9999AAA',
            'vehicle_brand' => 'Toyota',
            'complaint' => 'Tune up rutin',
            'booking_date' => now()->toDateString(),
            'status' => 'pending',
            'service_fee' => 200000,
            'total_cost' => 200000,
        ]);

        // Customer A can view own order and booking
        $this->actingAs($customerA)->get(route('store.orders.show', $orderA))->assertSuccessful();
        $this->actingAs($customerA)->get(route('bookings.show', $bookingA))->assertSuccessful();

        // Customer B is forbidden (403) from viewing or cancelling Customer A's order
        $this->actingAs($customerB)->get(route('store.orders.show', $orderA))->assertForbidden();
        $this->actingAs($customerB)->post(route('store.orders.cancel', $orderA))->assertForbidden();

        // Customer B is forbidden (403) from viewing or paying Customer A's booking
        $this->actingAs($customerB)->get(route('bookings.show', $bookingA))->assertForbidden();
        $this->actingAs($customerB)->post(route('bookings.pay', $bookingA), ['pin' => '123456'])->assertForbidden();
    }
}
