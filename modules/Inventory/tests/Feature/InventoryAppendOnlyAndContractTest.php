<?php

declare(strict_types=1);

namespace Modules\Inventory\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Store\Domain\Models\Product;
use Tests\TestCase;

class InventoryAppendOnlyAndContractTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);
        $this->service = app(InventoryService::class);
    }

    private function createProduct(int $stock = 20): Product
    {
        $product = Product::create([
            'uuid' => (string) Str::uuid(),
            'sku' => 'TEST-'.Str::random(6),
            'name' => 'Busi Iridium Racing',
            'slug' => 'busi-iridium-'.Str::random(6),
            'description' => 'Busi pengapian presisi',
            'price' => 75_000,
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

    public function test_commit_dan_release_membuat_baris_stock_movement_baru_yang_menautkan_reservasi_tanpa_memutasi_baris_lama(): void
    {
        $product = $this->createProduct(20);

        // 1. Reservasi awal
        $res1 = $this->service->reserve($product->id, 5, 'order', 101, 'Reservasi pesanan #101');
        $this->assertSame(15, $product->fresh()->cached_stock);
        $this->assertSame(StockMovementReason::RESERVATION, $res1->reason);

        // 2. Commit reservasi
        $commit = $this->service->commit($res1->id, StockMovementReason::SALE, 'Commit penjualan #101');
        $this->assertNotSame($res1->id, $commit->id, 'Commit wajib membuat record StockMovement baru');
        $this->assertSame($res1->id, $commit->reference_movement_id, 'Commit wajib menautkan reference_movement_id ke reservasi');
        $this->assertSame(StockMovementReason::SALE, $commit->reason);
        $this->assertSame(0, $commit->qty, 'Commit tidak boleh mengurangi stok ganda');
        $this->assertSame(15, $product->fresh()->cached_stock);

        // Pastikan baris reservasi lama TIDAK dimutasi
        $res1Fresh = $res1->fresh();
        $this->assertSame(StockMovementReason::RESERVATION, $res1Fresh->reason);
        $this->assertSame(-5, $res1Fresh->qty);
        $this->assertSame('Reservasi pesanan #101', $res1Fresh->note);

        // 3. Reservasi kedua dan pelepasan (release)
        $res2 = $this->service->reserve($product->id, 4, 'order', 102, 'Reservasi pesanan #102');
        $this->assertSame(11, $product->fresh()->cached_stock);

        $release = $this->service->release($res2->id, 'Batal bayar');
        $this->assertNotSame($res2->id, $release->id, 'Release wajib membuat record StockMovement baru');
        $this->assertSame($res2->id, $release->reference_movement_id, 'Release wajib menautkan reference_movement_id ke reservasi');
        $this->assertSame(StockMovementReason::RESERVATION_RELEASE, $release->reason);
        $this->assertSame(4, $release->qty);
        $this->assertSame(15, $product->fresh()->cached_stock, 'Stok wajib dipulihkan setelah release');

        // Pastikan baris reservasi kedua lama TIDAK dimutasi
        $res2Fresh = $res2->fresh();
        $this->assertSame(StockMovementReason::RESERVATION, $res2Fresh->reason);
        $this->assertSame(-4, $res2Fresh->qty);
        $this->assertSame('Reservasi pesanan #102', $res2Fresh->note);
    }

    public function test_memanggil_commit_atau_release_dua_kali_pada_reservasi_yang_sama_bersifat_idempoten(): void
    {
        $product = $this->createProduct(10);

        // Reservasi 1 -> Commit dua kali
        $res = $this->service->reserve($product->id, 3);
        $c1 = $this->service->commit($res->id, StockMovementReason::SALE);
        $c2 = $this->service->commit($res->id, StockMovementReason::SALE);

        $this->assertSame($c1->id, $c2->id, 'Commit berulang harus mengembalikan baris movement resolusi yang sama');
        $this->assertSame(1, StockMovement::where('reference_movement_id', $res->id)->count());
        $this->assertSame(7, $product->fresh()->cached_stock);

        // Reservasi 2 -> Release dua kali
        $resR = $this->service->reserve($product->id, 2);
        $this->assertSame(5, $product->fresh()->cached_stock);

        $r1 = $this->service->release($resR->id);
        $r2 = $this->service->release($resR->id);

        $this->assertSame($r1->id, $r2->id, 'Release berulang harus mengembalikan baris movement resolusi yang sama');
        $this->assertSame(1, StockMovement::where('reference_movement_id', $resR->id)->count());
        $this->assertSame(7, $product->fresh()->cached_stock, 'Stok tidak boleh dipulihkan lebih dari sekali');
    }

    public function test_model_stock_movement_menolak_update_dan_delete_secara_langsung(): void
    {
        $product = $this->createProduct(5);
        $movement = StockMovement::where('product_id', $product->id)->firstOrFail();

        // 1. Coba update
        $updateRejected = false;
        try {
            $movement->qty = 99;
            $movement->save();
        } catch (\RuntimeException $e) {
            $updateRejected = true;
            $this->assertStringContainsString('immutable', $e->getMessage());
        }
        $this->assertTrue($updateRejected, 'StockMovement model wajib menolak update');

        // 2. Coba delete
        $deleteRejected = false;
        try {
            $movement->delete();
        } catch (\RuntimeException $e) {
            $deleteRejected = true;
            $this->assertStringContainsString('immutable', $e->getMessage());
        }
        $this->assertTrue($deleteRejected, 'StockMovement model wajib menolak delete');
    }

    public function test_deduct_ingredient_dan_add_ingredient_bekerja_secara_atomik_lewat_kontrak_resto_dengan_unique_constraint_outlet_id_dan_ingredient_id(): void
    {
        $outletId = DB::table('resto_outlets')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'code' => 'OUT-TEST-01',
            'name' => 'Outlet Resto Padang Modern',
            'address' => 'Jl. Sudirman No. 10',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ingredientId = DB::table('resto_ingredients')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'sku' => 'ING-RENDANG-01',
            'name' => 'Daging Sapi Tenderloin',
            'base_unit' => 'gram',
            'category' => 'protein',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Initial available should be 0
        $this->assertSame('0.000000', $this->service->availableIngredient($ingredientId, $outletId));

        // 2. Add ingredient stock
        $this->service->addIngredient($ingredientId, $outletId, '5000.000000', StockMovementReason::PURCHASE, 'po', 1, 'Restok daging');
        $this->assertSame('5000.000000', $this->service->availableIngredient($ingredientId, $outletId));

        // Pastikan hanya tepat satu record pada resto_ingredient_stocks untuk pasangan outlet & ingredient
        $stockCount = DB::table('resto_ingredient_stocks')
            ->where('outlet_id', $outletId)
            ->where('ingredient_id', $ingredientId)
            ->count();
        $this->assertSame(1, $stockCount);

        // 3. Deduct ingredient stock
        $this->service->deductIngredient($ingredientId, $outletId, '1500.000000', StockMovementReason::PRODUCTION, 'batch', 1, 'Masak rendang');
        $this->assertSame('3500.000000', $this->service->availableIngredient($ingredientId, $outletId));

        // 4. Adjust ingredient stock
        $variance = $this->service->adjustIngredient($ingredientId, $outletId, '4000.000000', StockMovementReason::ADJUSTMENT, 'opname', 1, 'Opname stok');
        $this->assertSame('500.000000', $variance);
        $this->assertSame('4000.000000', $this->service->availableIngredient($ingredientId, $outletId));

        // 5. Deducting more than available throws InsufficientStockException
        $this->expectException(InsufficientStockException::class);
        $this->service->deductIngredient($ingredientId, $outletId, '99999.000000');
    }
}
