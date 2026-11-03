<?php

declare(strict_types=1);

namespace Tests\Perilaku\R2;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Store\Domain\Models\Product;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

#[Group('perilaku')]
class R2_4Test extends TestCase
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
            'name' => 'Busi Iridium Racing R24',
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

    public function test_klausul_1_commit_dan_release_membuat_baris_baru_tanpa_mengubah_baris_lama(): void
    {
        $product = $this->createProduct(20);

        // 1. Reservasi awal
        $res = $this->service->reserve($product->id, 5, 'order', 101, 'Reservasi pesanan #101');
        $oldRowHash = md5(json_encode($res->fresh()->toArray()));

        // 2. Commit reservasi
        $commitMovement = $this->service->commit($res->id, StockMovementReason::SALE, 'Komit pengiriman');

        // Klausul 1 Terbukti: baris lama tidak berubah sama sekali
        $this->assertEquals($oldRowHash, md5(json_encode($res->fresh()->toArray())), 'Klausul 1: Baris reservasi lama tidak boleh berubah');

        // Dan baris baru dibuat dengan reference_movement_id
        $this->assertNotEquals($res->id, $commitMovement->id, 'Klausul 1: Baris movement baru harus dibuat');
        $this->assertEquals($res->id, $commitMovement->reference_movement_id, 'Klausul 1: Menautkan ID reservasi asal');
        $this->assertEquals(StockMovementReason::SALE, $commitMovement->reason);
    }

    public function test_klausul_2_dan_4_kontrak_resto_stok_atomik_dan_unique_index(): void
    {
        $outletId = DB::table('resto_outlets')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'code' => 'OUT-R24',
            'name' => 'Outlet R24 Test',
            'type' => 'dine_in',
            'address' => 'Jl. Lambung Mangkurat No. 1',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ingredientId = DB::table('resto_ingredients')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'sku' => 'ING-R24-01',
            'name' => 'Bahan Baku Uji',
            'base_unit' => 'gram',
            'category' => 'protein',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Tambah stok pertama
        $this->service->addIngredient($ingredientId, $outletId, '5000.000000', StockMovementReason::PURCHASE, 'po', 1, 'Restok awal');
        $this->assertSame('5000.000000', $this->service->availableIngredient($ingredientId, $outletId));

        // 2. Tambah stok kedua ke pasangan outlet & ingredient yang sama
        $this->service->addIngredient($ingredientId, $outletId, '2500.000000', StockMovementReason::PURCHASE, 'po', 2, 'Restok kedua');
        $this->assertSame('7500.000000', $this->service->availableIngredient($ingredientId, $outletId));

        // Klausul 2 Terbukti: Tepat satu record pada resto_ingredient_stocks untuk pasangan outlet & ingredient
        $stockCount = DB::table('resto_ingredient_stocks')
            ->where('outlet_id', $outletId)
            ->where('ingredient_id', $ingredientId)
            ->count();
        $this->assertSame(1, $stockCount, 'Klausul 2: Unique constraint memastikan tepat satu baris per (outlet_id, ingredient_id)');
    }

    public function test_klausul_3_model_stock_movement_menolak_update_dan_delete_secara_langsung(): void
    {
        $product = $this->createProduct(10);
        $movement = StockMovement::where('product_id', $product->id)->firstOrFail();

        $updateBlocked = false;
        try {
            $movement->update(['qty' => 999]);
        } catch (RuntimeException $e) {
            $updateBlocked = true;
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
        $this->assertTrue($updateBlocked, 'Klausul 3: Update langsung pada StockMovement wajib ditolak');

        $deleteBlocked = false;
        try {
            $movement->delete();
        } catch (RuntimeException $e) {
            $deleteBlocked = true;
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
        $this->assertTrue($deleteBlocked, 'Klausul 3: Delete langsung pada StockMovement wajib ditolak');
    }
}
