<?php

declare(strict_types=1);

namespace Modules\Inventory\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Store\Domain\Models\Product;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
    }

    private function createProduct(int $stock = 10): Product
    {
        $product = Product::create([
            'uuid' => (string) Str::uuid(),
            'sku' => 'TEST-'.Str::random(6),
            'name' => 'Oli Mesin Sintetik 1L',
            'slug' => 'oli-mesin-sintetik-'.Str::random(6),
            'description' => 'Oli mesin kualitas terbaik',
            'price' => 150000,
            'cached_stock' => $stock,
            'is_listed' => true,
        ]);

        if ($stock > 0) {
            StockMovement::create([
                'product_id' => $product->id,
                'qty' => $stock,
                'reason' => StockMovementReason::INITIAL,
                'source_type' => 'test',
                'source_id' => 1,
                'note' => 'Stok awal test',
                'created_at' => now(),
            ]);
        }

        return $product;
    }

    public function test_available_mengembalikan_stok_terkini(): void
    {
        $product = $this->createProduct(15);
        $service = app(InventoryService::class);

        $this->assertSame(15, $service->available($product->id));
    }

    public function test_reserve_memotong_cached_stock_dan_mencatat_movement_negatif(): void
    {
        $product = $this->createProduct(10);
        $service = app(InventoryService::class);

        $movement = $service->reserve($product->id, 4, 'order', 99, 'Test reserve');

        $this->assertSame(-4, $movement->qty);
        $this->assertSame(StockMovementReason::RESERVATION, $movement->reason);
        $this->assertSame(6, $product->fresh()->cached_stock);
        $this->assertSame(6, $service->available($product->id));
    }

    public function test_reserve_melempar_insufficient_stock_exception_jika_stok_kurang(): void
    {
        $product = $this->createProduct(3);
        $service = app(InventoryService::class);

        $this->expectException(InsufficientStockException::class);
        $service->reserve($product->id, 5);
    }

    public function test_commit_mengubah_alasan_reservasi_menjadi_sale(): void
    {
        $product = $this->createProduct(10);
        $service = app(InventoryService::class);

        $res = $service->reserve($product->id, 3);
        $this->assertSame(7, $product->fresh()->cached_stock);

        $committed = $service->commit($res->id, StockMovementReason::SALE, 'Confirmed sale');

        $this->assertSame(StockMovementReason::SALE, $committed->fresh()->reason);
        $this->assertSame('Confirmed sale', $committed->fresh()->note);
        $this->assertSame(7, $product->fresh()->cached_stock); // Tidak berkurang ganda
    }

    public function test_release_mengembalikan_stok_dengan_movement_reservation_release(): void
    {
        $product = $this->createProduct(10);
        $service = app(InventoryService::class);

        $res = $service->reserve($product->id, 4);
        $this->assertSame(6, $product->fresh()->cached_stock);

        $release = $service->release($res->id, 'Batal checkout');

        $this->assertSame(4, $release->qty);
        $this->assertSame(StockMovementReason::RESERVATION_RELEASE, $release->reason);
        $this->assertSame(10, $product->fresh()->cached_stock);
    }

    public function test_adjust_menambah_atau_mengurangi_stok_dan_menolak_stok_negatif(): void
    {
        $product = $this->createProduct(5);
        $service = app(InventoryService::class);

        // Tambah 5 (restok)
        $service->adjust($product->id, 5, StockMovementReason::PURCHASE);
        $this->assertSame(10, $product->fresh()->cached_stock);

        // Kurangi 3 (penyesuaian manual)
        $service->adjust($product->id, -3, StockMovementReason::ADJUSTMENT);
        $this->assertSame(7, $product->fresh()->cached_stock);

        // Coba kurangi 10 (melebihi 7)
        $this->expectException(InsufficientStockException::class);
        $service->adjust($product->id, -10, StockMovementReason::ADJUSTMENT);
    }

    public function test_dua_user_berebut_stok_terakhir_hanya_satu_yang_berhasil(): void
    {
        $product = $this->createProduct(1);
        $service = app(InventoryService::class);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 reservasi berhasil
        $res1 = $service->reserve($product->id, 1, 'order', 1, null, $user1->id);
        $this->assertNotNull($res1);
        $this->assertSame(0, $product->fresh()->cached_stock);

        // User 2 mencoba reservasi unit yang sama -> InsufficientStockException
        $this->expectException(InsufficientStockException::class);
        $service->reserve($product->id, 1, 'order', 2, null, $user2->id);
    }
}
