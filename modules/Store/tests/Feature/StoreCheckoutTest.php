<?php

declare(strict_types=1);

namespace Modules\Store\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\AutoDex\Domain\Models\Brand;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Payment\Domain\Enums\PaymentIntentStatus;
use Modules\Store\Application\Actions\CancelOrderAction;
use Modules\Store\Application\Actions\ListCarProductAction;
use Modules\Store\Application\Services\CartService;
use Modules\Store\Application\Services\CheckoutService;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;
use Modules\Store\Domain\Models\OrderItem;
use Modules\Store\Domain\Models\Product;
use Tests\TestCase;

class StoreCheckoutTest extends TestCase
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

    private function createProduct(int $stock = 10, int $price = 50000, bool $isCar = false): Product
    {
        $product = Product::create([
            'uuid' => (string) Str::uuid(),
            'sku' => 'SKU-'.Str::random(6),
            'name' => 'Produk Uji '.Str::random(4),
            'slug' => 'produk-uji-'.Str::random(6),
            'price' => $price,
            'cached_stock' => $stock,
            'is_listed' => true,
            'is_car' => $isCar,
            'weight_gram' => 500,
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

    public function test_checkout_sukses_memotong_saldo_dan_mengubah_reservasi_menjadi_penjualan(): void
    {
        $user = $this->createCustomerWithWallet(500000, '123456');
        $product = $this->createProduct(stock: 10, price: 100000);

        // Tambah ke cart via CartService
        $cartService = app(CartService::class);
        $cartService->addItem($user, $product->id, 2);

        $checkoutService = app(CheckoutService::class);
        $shipping = [
            'name' => 'Budi Santoso',
            'phone' => '08123456789',
            'address' => 'Jl. Merdeka No. 10',
            'city' => 'Jakarta Pusat',
            'postal_code' => '10110',
        ];

        $order = $checkoutService->checkout($user, $shipping, '123456');

        // Order asserts
        $this->assertSame(OrderStatus::PAID, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(200000, $order->subtotal); // 2 x 100k
        $this->assertSame(15000, $order->shipping_fee); // min 15k
        $this->assertSame(215000, $order->grand_total);

        // Cart is cleared
        $this->assertSame(0, $cartService->getOrCreateCart($user)->items()->count());

        // Stock is deducted to 8
        $this->assertSame(8, $product->fresh()->cached_stock);

        // Movement is committed as SALE
        $movement = StockMovement::where('product_id', $product->id)
            ->where('reason', StockMovementReason::SALE)
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame(-2, $movement->qty);

        // Payment intent captured
        $intent = $order->paymentIntents()->first();
        $this->assertNotNull($intent);
        $this->assertSame(PaymentIntentStatus::CAPTURED, $intent->status);

        // Wallet balance reduced by 215.000
        $this->assertSame(500000 - 215000, (int) (string) $user->fresh()->walletBalance('IDR')->amount);
    }

    public function test_checkout_ditolak_jika_stok_tidak_mencukupi(): void
    {
        $user = $this->createCustomerWithWallet(1000000, '123456');
        $product = $this->createProduct(stock: 2, price: 50000);

        $cartService = app(CartService::class);

        $this->expectException(InsufficientStockException::class);
        $cartService->addItem($user, $product->id, 5);
    }

    public function test_checkout_ditolak_jika_saldo_kurang_dan_reservasi_stok_dilepaskan_kembali(): void
    {
        // Saldo hanya 50.000, grand total > 100.000
        $user = $this->createCustomerWithWallet(50000, '123456');
        $product = $this->createProduct(stock: 5, price: 80000);

        $cartService = app(CartService::class);
        $cartService->addItem($user, $product->id, 1);

        $checkoutService = app(CheckoutService::class);
        $shipping = [
            'name' => 'Budi Santoso',
            'phone' => '08123456789',
            'address' => 'Jl. Merdeka No. 10',
            'city' => 'Jakarta Pusat',
            'postal_code' => '10110',
        ];

        try {
            $checkoutService->checkout($user, $shipping, '123456');
            $this->fail('Harusnya melempar exception saldo tidak mencukupi.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Saldo tidak mencukupi', $e->getMessage());
        }

        // Pastikan stok kembali ke 5 karena reservasi di-rollback!
        $this->assertSame(5, $product->fresh()->cached_stock);

        // Movement reservasi release tercatat
        $hasRelease = StockMovement::where('product_id', $product->id)
            ->where('reason', StockMovementReason::RESERVATION_RELEASE)
            ->exists();
        $this->assertTrue($hasRelease);
    }

    public function test_dua_user_berebut_stok_terakhir_via_checkout(): void
    {
        $user1 = $this->createCustomerWithWallet(500000, '123456');
        $user2 = $this->createCustomerWithWallet(500000, '123456');
        $product = $this->createProduct(stock: 1, price: 100000);

        $cartService = app(CartService::class);
        $checkoutService = app(CheckoutService::class);
        $shipping = [
            'name' => 'Customer',
            'phone' => '08123456789',
            'address' => 'Alamat',
            'city' => 'Jakarta',
            'postal_code' => '12345',
        ];

        // User 1 beli unit terakhir
        $cartService->addItem($user1, $product->id, 1);
        $order1 = $checkoutService->checkout($user1, $shipping, '123456');
        $this->assertSame(OrderStatus::PAID, $order1->status);
        $this->assertSame(0, $product->fresh()->cached_stock);

        // User 2 mencoba menambahkan ke cart
        $this->expectException(InsufficientStockException::class);
        $cartService->addItem($user2, $product->id, 1);
    }

    public function test_command_store_cancel_stale_orders_membatalkan_dan_melepas_reservasi(): void
    {
        $user = $this->createCustomerWithWallet(500000, '123456');
        $product = $this->createProduct(stock: 10, price: 50000);

        $inventoryService = app(InventoryService::class);

        // Reservasi stok 3 unit
        $res = $inventoryService->reserve($product->id, 3);
        $this->assertSame(7, $product->fresh()->cached_stock);

        // Buat order pending_payment yang berumur 35 menit yang lalu
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'subtotal' => 150000,
            'shipping_fee' => 15000,
            'grand_total' => 165000,
        ]);

        Order::where('id', $order->id)->update([
            'created_at' => now()->subMinutes(35),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name_snapshot' => $product->name,
            'price_snapshot' => $product->price,
            'qty' => 3,
            'line_total' => 150000,
            'reservation_id' => $res->id,
        ]);

        // Jalankan artisan command
        $this->artisan('store:cancel-stale-orders')
            ->assertSuccessful();

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->cancelled_at);

        // Stok 3 unit dikembalikan sehingga kembali menjadi 10!
        $this->assertSame(10, $product->fresh()->cached_stock);
    }

    public function test_beli_mobil_via_store_membuat_vehicle_dan_menghapus_dari_wishlist(): void
    {
        $brand = Brand::create([
            'name' => 'Porsche',
            'slug' => 'porsche-'.Str::random(4),
            'category' => 'euro',
        ]);

        $car = Car::create([
            'brand_id' => $brand->id,
            'model' => '911 GT3 RS',
            'slug' => '911-gt3-rs-'.Str::random(4),
            'year_start' => 2024,
            'price_idr' => 20000000, // Rp 20 juta (dalam rupiah)
            'is_active' => true,
        ]);

        $user = $this->createCustomerWithWallet(30000000, '123456');

        // User masukkan mobil ke wishlist
        $user->wishlistCars()->attach($car->id);
        $this->assertTrue($user->hasInWishlist($car->id));

        // Admin listing mobil ke Store
        $action = app(ListCarProductAction::class);
        $carProduct = $action->execute($car, price: 20000000, stock: 1);

        $this->assertTrue($carProduct->is_car);
        $this->assertSame(1, $carProduct->cached_stock);

        // User beli mobil
        $cartService = app(CartService::class);
        $cartService->addItem($user, $carProduct->id, 1);

        $checkoutService = app(CheckoutService::class);
        $order = $checkoutService->checkout($user, [
            'name' => 'Sultan Mobil',
            'phone' => '08123456789',
            'address' => 'Kawasan Elit Blok A',
            'city' => 'Jakarta Selatan',
            'postal_code' => '12190',
        ], '123456');

        // Karena mobil, order langsung COMPLETED dan trigger fulfillment
        $this->assertSame(OrderStatus::COMPLETED, $order->status);

        // Vehicle tercipta di My Garage!
        $vehicle = Vehicle::where('user_id', $user->id)
            ->where('car_id', $car->id)
            ->first();

        $this->assertNotNull($vehicle);
        $this->assertSame('order', $vehicle->acquired_via_type);
        $this->assertSame($order->id, $vehicle->acquired_via_id);

        // Mobil otomatis dihapus dari Wishlist!
        $this->assertFalse($user->fresh()->hasInWishlist($car->id));
        $this->assertTrue($user->fresh()->hasInGarage($car->id));
    }

    public function test_pembatalan_dan_refund_pesanan_mengembalikan_dana_dan_stok(): void
    {
        $user = $this->createCustomerWithWallet(500000, '123456');
        $product = $this->createProduct(stock: 10, price: 100000);

        $cartService = app(CartService::class);
        $cartService->addItem($user, $product->id, 2);

        $checkoutService = app(CheckoutService::class);
        $order = $checkoutService->checkout($user, [
            'name' => 'Budi',
            'phone' => '08123456789',
            'address' => 'Jl. Test',
            'city' => 'Jakarta',
            'postal_code' => '12345',
        ], '123456');

        $this->assertSame(OrderStatus::PAID, $order->status);
        $this->assertSame(8, $product->fresh()->cached_stock);
        $initialBalanceAfterBuy = (int) (string) $user->fresh()->walletBalance('IDR')->amount;

        // Batalkan pesanan via CancelOrderAction
        $cancelAction = app(CancelOrderAction::class);
        $refundedOrder = $cancelAction->execute($order, 'Permintaan pembatalan customer');

        $this->assertSame(OrderStatus::REFUNDED, $refundedOrder->status);

        // Stok 2 unit dipulihkan dengan reason RETURN
        $this->assertSame(10, $product->fresh()->cached_stock);
        $returnMovement = StockMovement::where('product_id', $product->id)
            ->where('reason', StockMovementReason::RETURN)
            ->first();
        $this->assertNotNull($returnMovement);
        $this->assertSame(2, $returnMovement->qty);

        // Saldo dikembalikan penuh ke dompet pembeli
        $finalBalance = (int) (string) $user->fresh()->walletBalance('IDR')->amount;
        $this->assertSame($initialBalanceAfterBuy + $order->grand_total, $finalBalance);
    }

    public function test_rekonsiliasi_bank_ledger_tetap_bersih_setelah_checkout_dan_refund_store(): void
    {
        $this->artisan('bank:reconcile')
            ->assertSuccessful();
    }
}
