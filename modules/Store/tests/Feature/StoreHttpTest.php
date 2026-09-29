<?php

declare(strict_types=1);

namespace Modules\Store\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;
use Modules\Store\Domain\Models\Product;
use Tests\TestCase;

class StoreHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
    }

    private function createCustomerWithWallet(int $balance = 1000000, string $pin = '123456'): User
    {
        $user = User::factory()->create([
            'role' => 'customer',
        ]);

        app(SetPinAction::class)->execute($user, $pin);

        if ($balance > 0) {
            app(TopUpAction::class)->execute($user, $balance);
        }

        return $user;
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }

    private function createProduct(int $stock = 10, int $price = 50000): Product
    {
        $product = Product::create([
            'uuid' => (string) Str::uuid(),
            'sku' => 'SKU-'.Str::random(6),
            'name' => 'Oli Mesin Racing 1L',
            'slug' => 'oli-mesin-racing-'.Str::random(6),
            'price' => $price,
            'cached_stock' => $stock,
            'is_listed' => true,
            'weight_gram' => 1000,
        ]);

        if ($stock > 0) {
            StockMovement::create([
                'product_id' => $product->id,
                'qty' => $stock,
                'reason' => StockMovementReason::INITIAL,
                'source_type' => 'test',
                'source_id' => 1,
                'note' => 'Stok awal',
                'created_at' => now(),
            ]);
        }

        return $product;
    }

    public function test_katalog_store_dapat_diakses_dan_mengembalikan_json_untuk_ajax(): void
    {
        $product = $this->createProduct(stock: 5, price: 120000);

        // HTML response
        $response = $this->get('/store');
        $response->assertStatus(200);
        $response->assertSee('Katalog Produk');
        $response->assertSee($product->name);

        // JSON response untuk live search
        $ajaxResponse = $this->getJson('/store?search='.urlencode(substr($product->name, 0, 5)));
        $ajaxResponse->assertStatus(200)
            ->assertJsonStructure([
                'products',
                'pagination' => ['current_page', 'last_page', 'total'],
            ]);
    }

    public function test_halaman_detail_produk_dapat_diakses(): void
    {
        $product = $this->createProduct(stock: 5, price: 120000);

        $response = $this->get('/store/products/'.$product->slug);
        $response->assertStatus(200);
        $response->assertSee($product->name);
        $response->assertSee('SKU: '.$product->sku);
    }

    public function test_alur_keranjang_belanja_via_http(): void
    {
        $user = $this->createCustomerWithWallet();
        $product = $this->createProduct(stock: 10, price: 100000);

        // 1. Add to cart via JSON POST
        $resAdd = $this->actingAs($user)->postJson('/store/cart', [
            'product_id' => $product->id,
            'qty' => 2,
        ]);
        $resAdd->assertStatus(200)
            ->assertJson(['success' => true]);

        // 2. Check cart count
        $resCount = $this->actingAs($user)->getJson('/store/cart/count');
        $resCount->assertStatus(200)
            ->assertJson(['count' => 2]);

        // 3. View cart page
        $resView = $this->actingAs($user)->get('/store/cart');
        $resView->assertStatus(200)
            ->assertSee($product->name);

        // 4. Update qty via PATCH
        $resUpdate = $this->actingAs($user)->patchJson('/store/cart/'.$product->id, [
            'qty' => 3,
        ]);
        $resUpdate->assertStatus(200)
            ->assertJson(['success' => true]);

        // 5. Remove item from cart
        $resDelete = $this->actingAs($user)->deleteJson('/store/cart/'.$product->id);
        $resDelete->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_checkout_dan_pembayaran_berhasil_via_http(): void
    {
        $user = $this->createCustomerWithWallet(500000, '123456');
        $product = $this->createProduct(stock: 10, price: 100000);

        // Add to cart
        $this->actingAs($user)->postJson('/store/cart', [
            'product_id' => $product->id,
            'qty' => 1,
        ]);

        // View checkout page
        $resCheckoutPage = $this->actingAs($user)->get('/store/checkout');
        $resCheckoutPage->assertStatus(200)
            ->assertSee('Alamat Pengiriman');

        // Submit checkout
        $resProcess = $this->actingAs($user)->post('/store/checkout', [
            'recipient_name' => 'Budi Santoso',
            'phone' => '08123456789',
            'address' => 'Jl. Kebon Jeruk No. 5',
            'city' => 'Jakarta Barat',
            'postal_code' => '11530',
            'pin' => '123456',
        ]);

        $order = Order::latest()->first();
        $this->assertNotNull($order);
        $resProcess->assertRedirect(route('store.orders.show', $order));

        $this->assertSame(OrderStatus::PAID, $order->status);
    }

    public function test_customer_dapat_melihat_daftar_dan_detail_pesanannya(): void
    {
        $user = $this->createCustomerWithWallet(500000, '123456');
        $product = $this->createProduct(stock: 10, price: 100000);

        $this->actingAs($user)->postJson('/store/cart', [
            'product_id' => $product->id,
            'qty' => 1,
        ]);

        $this->actingAs($user)->post('/store/checkout', [
            'recipient_name' => 'Budi',
            'phone' => '08123456789',
            'address' => 'Jl. Budi',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'pin' => '123456',
        ]);

        $order = Order::latest()->first();

        // Customer sees orders list
        $resList = $this->actingAs($user)->get('/store/orders');
        $resList->assertStatus(200)
            ->assertSee($order->number);

        // Customer sees order detail
        $resShow = $this->actingAs($user)->get('/store/orders/'.$order->uuid);
        $resShow->assertStatus(200)
            ->assertSee($order->number)
            ->assertSee('Progres Pesanan');
    }

    public function test_admin_dapat_mengelola_produk_dan_mengirim_pesanan(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomerWithWallet(500000, '123456');
        $product = $this->createProduct(stock: 10, price: 100000);

        // Customer order
        $this->actingAs($customer)->postJson('/store/cart', [
            'product_id' => $product->id,
            'qty' => 1,
        ]);
        $this->actingAs($customer)->post('/store/checkout', [
            'recipient_name' => 'Customer',
            'phone' => '08123',
            'address' => 'Jl. Test',
            'city' => 'Jakarta',
            'postal_code' => '123',
            'pin' => '123456',
        ]);
        $order = Order::latest()->first();

        // 1. Admin accesses orders list
        $resOrders = $this->actingAs($admin)->get('/admin/store/orders');
        $resOrders->assertStatus(200)
            ->assertSee($order->number);

        // 2. Admin ships order
        $resShip = $this->actingAs($admin)->post('/admin/store/orders/'.$order->uuid.'/ship', [
            'tracking_number' => 'JNE-99887766',
        ]);
        $resShip->assertRedirect();
        $this->assertSame(OrderStatus::SHIPPED, $order->fresh()->status);
        $this->assertSame('JNE-99887766', $order->fresh()->tracking_number);

        // 3. Admin accesses products list
        $resProducts = $this->actingAs($admin)->get('/admin/store/products');
        $resProducts->assertStatus(200)
            ->assertSee($product->name);

        // 4. Admin toggles product listing
        $this->actingAs($admin)->post('/admin/store/products/'.$product->id.'/toggle');
        $this->assertFalse($product->fresh()->is_listed);

        // 5. Admin adjusts stock
        $this->actingAs($admin)->post('/admin/store/products/'.$product->id.'/adjust-stock', [
            'qty' => 5,
            'reason' => 'purchase',
            'note' => 'Restok suplai baru',
        ]);
        $this->assertSame(14, $product->fresh()->cached_stock); // 10 - 1 (bought) + 5 = 14
    }

    public function test_customer_biasa_dilarang_mengakses_rute_admin_store(): void
    {
        $customer = $this->createCustomerWithWallet();

        $response = $this->actingAs($customer)->get('/admin/store/products');
        $response->assertStatus(403);

        $responseOrders = $this->actingAs($customer)->get('/admin/store/orders');
        $responseOrders->assertStatus(403);
    }
}
