<?php

declare(strict_types=1);

namespace Modules\Store\tests\Feature;

use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AutoDex\Domain\Models\Brand;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Application\Actions\AcquireVehicleAction;
use Modules\Core\Domain\Enums\VehicleEventType;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Core\Domain\Models\VehicleEvent;
use Modules\Payment\Domain\Enums\PaymentIntentStatus;
use Modules\Store\Application\Actions\CancelC2cOrderAction;
use Modules\Store\Application\Actions\ConfirmC2cReceiptAction;
use Modules\Store\Application\Actions\DisputeC2cOrderAction;
use Modules\Store\Application\Actions\ListVehicleForSaleAction;
use Modules\Store\Application\Actions\MarkC2cHandoverAction;
use Modules\Store\Application\Actions\PurchaseC2cVehicleAction;
use Modules\Store\Application\Actions\ResolveC2cDisputeAction;
use Modules\Store\Application\Services\CartService;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;
use Modules\Store\Domain\Models\Product;
use Tests\TestCase;

class C2cEscrowTest extends TestCase
{
    use RefreshDatabase;

    private const PRICE = 200_000_000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
    }

    private function makeUser(string $name, int $balance = 0, string $pin = '123456'): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'role' => 'customer',
        ]);

        app(SetPinAction::class)->execute($user, $pin);

        // Top up dibatasi Rp 50 juta per transaksi, jadi isi saldo bertahap
        $topUp = app(TopUpAction::class);
        $remaining = $balance;
        while ($remaining > 0) {
            $chunk = min($remaining, 50_000_000);
            $topUp->execute($user, $chunk);
            $remaining -= $chunk;
        }

        return $user;
    }

    private function makeVehicle(User $owner): Vehicle
    {
        $brand = Brand::create([
            'name' => 'Mazda',
            'slug' => 'mazda-c2c-'.$owner->id,
            'country' => 'Japan',
            'category' => 'jdm',
        ]);

        $car = Car::create([
            'brand_id' => $brand->id,
            'model' => 'RX-7 FD',
            'slug' => 'mazda-rx7-fd-c2c-'.$owner->id,
            'year_start' => 1995,
            'body_type' => 'Coupe',
            'fuel_type' => 'gasoline',
            'is_active' => true,
        ]);

        return app(AcquireVehicleAction::class)->handle(
            user: $owner,
            car: $car,
            plateNumber: 'B 7 RX'.$owner->id,
            color: 'Montego Blue',
            vin: 'JM1FD3'.str_pad((string) $owner->id, 11, '0', STR_PAD_LEFT),
            odometerKm: 120000,
            acquiredViaType: 'manual',
        );
    }

    private function listing(User $seller, Vehicle $vehicle, int $price = self::PRICE): Product
    {
        return app(ListVehicleForSaleAction::class)->execute($seller, $vehicle, $price);
    }

    private function shippingAddress(): array
    {
        return [
            'name' => 'Pembeli Uji',
            'phone' => '08123456789',
            'address' => 'Jl. Kenangan No. 1',
            'city' => 'Jakarta',
            'postal_code' => '12345',
        ];
    }

    private function buy(User $buyer, Product $product): Order
    {
        return app(PurchaseC2cVehicleAction::class)->execute(
            buyer: $buyer,
            product: $product,
            shippingAddress: $this->shippingAddress(),
            pin: '123456',
        );
    }

    private function escrowBalance(): string
    {
        return (string) LedgerAccount::where('code', 'escrow:payment:IDR')->value('cached_balance');
    }

    private function walletBalance(User $user): int
    {
        return (int) $user->walletAccount('IDR')->fresh()->cached_balance;
    }

    public function test_alur_lengkap_c2c_dari_listing_sampai_kepemilikan_berpindah(): void
    {
        $seller = $this->makeUser('Penjual');
        $buyer = $this->makeUser('Pembeli', self::PRICE + 5_000_000);
        $vehicle = $this->makeVehicle($seller);

        $product = $this->listing($seller, $vehicle);
        $this->assertTrue($product->isC2c());
        $this->assertSame(1, (int) $product->cached_stock);

        $buyerBalanceBefore = $this->walletBalance($buyer);

        // 1. Pembeli checkout: dana masuk escrow
        $order = $this->buy($buyer, $product);

        $this->assertSame(OrderStatus::AWAITING_HANDOVER, $order->status);
        $this->assertSame((int) $seller->id, (int) $order->seller_id);
        $this->assertSame($buyerBalanceBefore - self::PRICE, $this->walletBalance($buyer));
        $this->assertSame(0, (int) $this->walletBalance($seller));
        $this->assertSame((float) self::PRICE, (float) $this->escrowBalance());
        $this->assertSame(0, (int) $product->fresh()->cached_stock);

        // 2. Penjual menyerahkan kendaraan
        $order = app(MarkC2cHandoverAction::class)->execute($order, $seller);
        $this->assertSame(OrderStatus::AWAITING_CONFIRMATION, $order->status);
        $this->assertNotNull($order->auto_capture_at);

        // 3. Pembeli konfirmasi terima: escrow cair 99% ke penjual, 1% fee platform
        $order = app(ConfirmC2cReceiptAction::class)->execute($order, $buyer);

        $this->assertSame(OrderStatus::COMPLETED, $order->status);
        $this->assertSame(0.0, (float) $this->escrowBalance());

        $expectedFee = intdiv(self::PRICE, 100);
        $this->assertSame(self::PRICE - $expectedFee, $this->walletBalance($seller));
        $this->assertSame(
            (float) $expectedFee,
            (float) LedgerAccount::where('code', 'revenue:store:IDR')->value('cached_balance')
        );

        // 4. Kepemilikan kendaraan berpindah dan listing nonaktif
        $vehicle->refresh();
        $this->assertSame((int) $buyer->id, (int) $vehicle->user_id);
        $this->assertSame('store_order', $vehicle->acquired_via_type);
        $this->assertFalse((bool) $product->fresh()->is_listed);

        // 5. Blok ownership_transferred tercatat di paspor
        $transferEvent = VehicleEvent::where('vehicle_id', $vehicle->id)
            ->where('type', VehicleEventType::OWNERSHIP_TRANSFERRED->value)
            ->first();

        $this->assertNotNull($transferEvent);
        $this->assertSame((int) $seller->id, $transferEvent->payload['from_user_id']);
        $this->assertSame((int) $buyer->id, $transferEvent->payload['to_user_id']);
        $this->assertSame(self::PRICE, $transferEvent->payload['price_idr']);

        $this->artisan('bank:reconcile')->assertSuccessful();
        $this->artisan('core:verify-passports')->assertSuccessful();
    }

    public function test_pembeli_tidak_dapat_membeli_listing_miliknya_sendiri(): void
    {
        $seller = $this->makeUser('Penjual Sendiri', self::PRICE + 1_000_000);
        $vehicle = $this->makeVehicle($seller);
        $product = $this->listing($seller, $vehicle);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Kamu tidak dapat membeli kendaraanmu sendiri.');

        $this->buy($seller, $product);
    }

    public function test_saldo_kurang_membatalkan_order_dan_melepas_unit_kembali(): void
    {
        $seller = $this->makeUser('Penjual Miskin');
        $buyer = $this->makeUser('Pembeli Kurang Saldo', 1_000_000);
        $vehicle = $this->makeVehicle($seller);
        $product = $this->listing($seller, $vehicle);

        try {
            $this->buy($buyer, $product);
            $this->fail('Pembelian seharusnya gagal karena saldo tidak cukup.');
        } catch (\Throwable) {
            // diharapkan
        }

        $order = Order::where('seller_id', $seller->id)->latest()->firstOrFail();

        $this->assertSame(OrderStatus::CANCELLED, $order->status);
        $this->assertSame(1, (int) $product->fresh()->cached_stock);
        $this->assertSame(0.0, (float) $this->escrowBalance());
        $this->assertSame((int) $seller->id, (int) $vehicle->fresh()->user_id);
    }

    public function test_pembatalan_sebelum_serah_terima_mengembalikan_dana_penuh_dan_mengaktifkan_listing(): void
    {
        $seller = $this->makeUser('Penjual Batal');
        $buyer = $this->makeUser('Pembeli Batal', self::PRICE);
        $vehicle = $this->makeVehicle($seller);
        $product = $this->listing($seller, $vehicle);

        $order = $this->buy($buyer, $product);
        $this->assertSame(0, $this->walletBalance($buyer));

        $order = app(CancelC2cOrderAction::class)->execute($order, 'Penjual tidak responsif');

        $this->assertSame(OrderStatus::CANCELLED, $order->status);
        $this->assertSame(self::PRICE, $this->walletBalance($buyer));
        $this->assertSame(0, $this->walletBalance($seller));
        $this->assertSame(0.0, (float) $this->escrowBalance());
        $this->assertSame(1, (int) $product->fresh()->cached_stock);
        $this->assertTrue((bool) $product->fresh()->is_listed);
        $this->assertSame((int) $seller->id, (int) $vehicle->fresh()->user_id);

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_sengketa_dimenangkan_pembeli_mengembalikan_dana_escrow(): void
    {
        $seller = $this->makeUser('Penjual Sengketa');
        $buyer = $this->makeUser('Pembeli Sengketa', self::PRICE);
        $vehicle = $this->makeVehicle($seller);
        $product = $this->listing($seller, $vehicle);

        $order = $this->buy($buyer, $product);
        $order = app(MarkC2cHandoverAction::class)->execute($order, $seller);
        $order = app(DisputeC2cOrderAction::class)->execute($order, $buyer, 'Kondisi mesin tidak sesuai deskripsi listing.');

        $this->assertSame(OrderStatus::DISPUTED, $order->status);
        $this->assertNull($order->auto_capture_at);

        $order = app(ResolveC2cDisputeAction::class)->execute($order, 'release', 'Bukti foto mendukung pembeli');

        $this->assertSame(OrderStatus::CANCELLED, $order->status);
        $this->assertSame(self::PRICE, $this->walletBalance($buyer));
        $this->assertSame((int) $seller->id, (int) $vehicle->fresh()->user_id);
        $this->assertSame(0.0, (float) $this->escrowBalance());

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_sengketa_dimenangkan_penjual_mencairkan_dana_dan_memindahkan_kepemilikan(): void
    {
        $seller = $this->makeUser('Penjual Menang');
        $buyer = $this->makeUser('Pembeli Kalah', self::PRICE);
        $vehicle = $this->makeVehicle($seller);
        $product = $this->listing($seller, $vehicle);

        $order = $this->buy($buyer, $product);
        $order = app(MarkC2cHandoverAction::class)->execute($order, $seller);
        $order = app(DisputeC2cOrderAction::class)->execute($order, $buyer, 'Surat kendaraan belum diserahkan.');

        $order = app(ResolveC2cDisputeAction::class)->execute($order, 'capture', 'Penjual membuktikan surat sudah lengkap');

        $this->assertSame(OrderStatus::COMPLETED, $order->status);
        $this->assertSame(self::PRICE - intdiv(self::PRICE, 100), $this->walletBalance($seller));
        $this->assertSame((int) $buyer->id, (int) $vehicle->fresh()->user_id);

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_dana_otomatis_cair_ke_penjual_setelah_tiga_hari_tanpa_konfirmasi(): void
    {
        $seller = $this->makeUser('Penjual Auto');
        $buyer = $this->makeUser('Pembeli Pasif', self::PRICE);
        $vehicle = $this->makeVehicle($seller);
        $product = $this->listing($seller, $vehicle);

        $order = $this->buy($buyer, $product);
        $order = app(MarkC2cHandoverAction::class)->execute($order, $seller);

        // Belum jatuh tempo: tidak ada pencairan
        $this->artisan('store:auto-capture-c2c')->assertSuccessful();
        $this->assertSame(OrderStatus::AWAITING_CONFIRMATION, $order->fresh()->status);

        $this->travel(Order::C2C_AUTO_CAPTURE_DAYS + 1)->days();

        $this->artisan('store:auto-capture-c2c')->assertSuccessful();

        $order->refresh();
        $this->assertSame(OrderStatus::COMPLETED, $order->status);
        $this->assertSame(self::PRICE - intdiv(self::PRICE, 100), $this->walletBalance($seller));
        $this->assertSame((int) $buyer->id, (int) $vehicle->fresh()->user_id);
        $this->assertSame(0.0, (float) $this->escrowBalance());

        $this->travelBack();
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_listing_c2c_tidak_bisa_dimasukkan_ke_keranjang_biasa(): void
    {
        $seller = $this->makeUser('Penjual Cart');
        $buyer = $this->makeUser('Pembeli Cart', self::PRICE);
        $vehicle = $this->makeVehicle($seller);
        $product = $this->listing($seller, $vehicle);

        $this->expectException(\InvalidArgumentException::class);

        app(CartService::class)->addItem($buyer, $product->id, 1);
    }

    public function test_kendaraan_yang_sedang_dijual_tidak_dapat_dipasang_dua_kali(): void
    {
        $seller = $this->makeUser('Penjual Ganda');
        $vehicle = $this->makeVehicle($seller);
        $this->listing($seller, $vehicle);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('sudah terpasang di Store');

        $this->listing($seller, $vehicle);
    }

    public function test_pembelian_c2c_via_http_menahan_dana_di_escrow(): void
    {
        $seller = $this->makeUser('Penjual Http');
        $buyer = $this->makeUser('Pembeli Http', self::PRICE);
        $vehicle = $this->makeVehicle($seller);
        $product = $this->listing($seller, $vehicle);

        $this->actingAs($buyer)
            ->get(route('store.c2c.index'))
            ->assertOk()
            ->assertSee($product->name);

        $this->actingAs($buyer)
            ->get(route('store.c2c.buyForm', $product))
            ->assertOk();

        $response = $this->actingAs($buyer)->post(route('store.c2c.buy', $product), [
            ...$this->shippingAddress(),
            'recipient_name' => 'Pembeli Http',
            'pin' => '123456',
        ]);

        $order = Order::where('seller_id', $seller->id)->latest()->firstOrFail();
        $response->assertRedirect(route('store.orders.show', $order));

        $this->assertSame(OrderStatus::AWAITING_HANDOVER, $order->status);
        $this->assertSame(
            PaymentIntentStatus::HELD,
            $order->paymentIntents()->latest()->first()->status
        );

        // Penjual dapat melihat pesanan dan menandai serah terima
        $this->actingAs($seller)
            ->post(route('store.c2c.handover', $order))
            ->assertRedirect(route('store.orders.show', $order));

        $this->assertSame(OrderStatus::AWAITING_CONFIRMATION, $order->fresh()->status);

        // Pembeli konfirmasi terima
        $this->actingAs($buyer)
            ->post(route('store.c2c.confirm', $order))
            ->assertRedirect(route('store.orders.show', $order));

        $this->assertSame(OrderStatus::COMPLETED, $order->fresh()->status);
        $this->assertSame((int) $buyer->id, (int) $vehicle->fresh()->user_id);
    }
}
