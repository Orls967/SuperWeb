<?php

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Asset\Application\Services\AssetService;
use Modules\Asset\Domain\Enums\AssetCategoryCode;
use Modules\Asset\Domain\Enums\AssetEventType;
use Modules\Asset\Domain\Enums\AssetStatus;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetCategory;
use Modules\Asset\Domain\Models\AssetLocation;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Tests\TestCase;

/**
 * Regresi Fase 30: register/kapitalisasi, hash-chain, mutasi (approval),
 * backfill idempoten, opname, check-out/in, asuransi.
 */
class AssetCoreTest extends TestCase
{
    use RefreshDatabase;

    private AssetService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
        $this->service = app(AssetService::class);
        $this->service->ensureDefaultCategories();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function category(string $code): AssetCategory
    {
        return AssetCategory::where('code', $code)->firstOrFail();
    }

    private function location(string $code, string $level, ?int $parentId = null): AssetLocation
    {
        return AssetLocation::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'level' => $level, 'parent_id' => $parentId],
        );
    }

    // ── 30.1 Kategori default ────────────────────────────────────────────

    public function test_default_categories_carry_useful_life_and_method(): void
    {
        $this->service->ensureDefaultCategories();

        foreach (AssetCategoryCode::cases() as $case) {
            $category = AssetCategory::where('code', $case->value)->firstOrFail();
            $this->assertSame($case->label(), $category->name);
            $this->assertSame($case->usefulLifeYears(), $category->useful_life_years);
            $this->assertSame($case->depreciationMethod(), $category->depreciation_method);
        }

        // Idempoten: pemanggilan ulang tidak menyalin kategori.
        $this->service->ensureDefaultCategories();
        $this->assertCount(count(AssetCategoryCode::cases()), AssetCategory::all());
    }

    // ── 30.2 + 30.3 Register & kapitalisasi ──────────────────────────────

    public function test_register_posts_acquisition_to_ledger_and_starts_chain(): void
    {
        $location = $this->location('AST-T-TEST', 'site');
        $category = $this->category(AssetCategoryCode::Building->value);

        $asset = $this->service->register([
            'name' => 'Gudang Uji Coba',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'acquisition_cost_idr' => 1_000_000_000,
            'landed_cost_idr' => 50_000_000,
            'source_type' => 'direct',
        ], $this->admin);

        $this->assertSame(1_050_000_000, $asset->book_value_idr);
        $this->assertStringStartsWith('AST/', $asset->asset_number);
        $this->assertNotNull($asset->asset_tag);

        // Chain: event akuisisi sebagai genesis.
        $this->assertSame(1, $asset->events()->count());
        $event = $asset->events()->firstOrFail();
        $this->assertSame(AssetEventType::Acquisition, $event->event_type);
        $this->assertSame(AssetService::GENESIS_HASH, $event->prev_hash);

        // Ledger: ast:fixed_assets bertambah 1.050.000.000.
        $fixed = LedgerAccount::where('code', 'ast:fixed_assets')->firstOrFail();
        $this->assertSame(1_050_000_000, (int) $fixed->money()->amount->toInt());

        // Idempotensi: key 'ast:acquire:{id}' mencegah posting ganda.
        $key = 'ast:acquire:'.$asset->id;
        $this->assertSame(1, LedgerTransaction::where('idempotency_key', $key)->count());

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_register_generates_unique_gapless_numbers(): void
    {
        $category = $this->category(AssetCategoryCode::Equipment->value);

        $numbers = [];
        for ($i = 0; $i < 3; $i++) {
            $asset = $this->service->register([
                'name' => "Peralatan {$i}",
                'category_id' => $category->id,
                'acquisition_cost_idr' => 10_000_000,
            ], null, false);

            $numbers[] = $asset->asset_number;
        }

        $this->assertCount(3, array_unique($numbers), 'Nomor aset harus gapless & unik.');
    }

    // ── 30.4 Hash-chain verifikasi ───────────────────────────────────────

    public function test_hash_chain_detects_tampered_event_payload(): void
    {
        $category = $this->category(AssetCategoryCode::Vehicle->value);
        $asset = $this->service->register([
            'name' => 'Truk Uji',
            'category_id' => $category->id,
            'acquisition_cost_idr' => 500_000_000,
        ], null, false);

        $this->service->recordEvent($asset, AssetEventType::Move, ['to_location_code' => 'A'], 'Tester');
        $this->service->recordEvent($asset, AssetEventType::Repair, ['note' => 'Ganti kampas rem'], 'Tester');

        // Rantai valid sebelum dimanipulasi.
        $result = $this->service->verifyChain($asset->id);
        $this->assertTrue($result['valid']);
        $this->assertSame(3, $result['checked']);

        // Manipulasi payload via DB mentah (melewati model guard).
        DB::table('ast_events')
            ->where('asset_id', $asset->id)
            ->where('sequence', 2)
            ->update(['payload' => json_encode(['note' => 'DIUBAH'])]);

        $tampered = $this->service->verifyChain($asset->id);
        $this->assertFalse($tampered['valid']);
        $this->assertSame(1, count($tampered['broken']));
    }

    public function test_asset_event_is_append_only(): void
    {
        $category = $this->category(AssetCategoryCode::It->value);
        $asset = $this->service->register([
            'name' => 'Server Uji',
            'category_id' => $category->id,
            'acquisition_cost_idr' => 50_000_000,
        ], null, false);

        $event = $asset->events()->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $event->delete();
    }

    // ── 30.5 Mutasi aset ────────────────────────────────────────────────

    public function test_move_requires_approval_then_executes(): void
    {
        $category = $this->category(AssetCategoryCode::Equipment->value);
        $from = $this->location('AST-FROM', 'area');
        $to = $this->location('AST-TO', 'area');

        $asset = $this->service->register([
            'name' => 'Mesin Pemindah',
            'category_id' => $category->id,
            'location_id' => $from->id,
            'acquisition_cost_idr' => 75_000_000,
        ], null, false);

        $pending = $this->service->requestMove($asset, $to->id, $this->admin, requiresApproval: true);
        $this->assertSame('pending', $pending['status']);
        $this->assertSame($from->id, $asset->fresh()->location_id); // belum bergerak

        // Setelah approval dieksekusi.
        $this->service->executeMove($asset, $to, 'Admin');
        $this->assertSame($to->id, $asset->fresh()->location_id);

        $moveEvents = $asset->events()->where('event_type', AssetEventType::Move)->count();
        $this->assertSame(1, $moveEvents);
    }

    public function test_move_rejects_disposed_asset(): void
    {
        $category = $this->category(AssetCategoryCode::Equipment->value);
        $a = $this->location('AST-LOC-A', 'area');
        $b = $this->location('AST-LOC-B', 'area');
        $asset = $this->service->register([
            'name' => 'Disposal Unit',
            'category_id' => $category->id,
            'location_id' => $a->id,
            'acquisition_cost_idr' => 1_000,
        ], null, false);
        $asset->update(['status' => AssetStatus::Disposed]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->requestMove($asset, $b->id, null, requiresApproval: false);
    }

    // ── 30.6 Backfill idempoten ─────────────────────────────────────────

    public function test_backfill_command_is_idempotent(): void
    {
        // Sumber data legacy berasal dari seeder platform (mall_assets, armada, dll.).
        $this->seed(DatabaseSeeder::class);

        // Jalankan dua kali: baris tercatat kedua kalinya harus 0.
        $this->artisan('ast:backfill-links')->assertSuccessful();
        $firstCount = Asset::where('source_type', 'legacy_backfill')->count();
        $this->assertGreaterThan(0, $firstCount, 'Backfill harus menautkan setidaknya satu aset lama.');

        $this->artisan('ast:backfill-links')->assertSuccessful();
        $secondCount = Asset::where('source_type', 'legacy_backfill')->count();

        $this->assertSame($firstCount, $secondCount, 'Backfill kedua kali tidak boleh membuat aset baru.');

        // Chain tetap valid untuk semua aset hasil backfill.
        $result = $this->service->verifyChain();
        $this->assertTrue($result['valid'], json_encode($result['broken']));
    }

    // ── 30.7 Stok opname ─────────────────────────────────────────────────

    public function test_stocktake_is_idempotent_per_cycle_and_flags_diff(): void
    {
        $category = $this->category(AssetCategoryCode::Equipment->value);
        $asset = $this->service->register([
            'name' => 'Laptop Opname',
            'category_id' => $category->id,
            'acquisition_cost_idr' => 15_000_000,
        ], null, false);

        $first = $this->service->recordStocktake($asset, 1, 'missing', $this->admin, 'Tidak ada di laci');
        $this->assertSame('pending', $first->adjustment_status);

        // Scan ganda dalam siklus yang sama → idempoten, tidak dobel.
        $second = $this->service->recordStocktake($asset, 1, 'missing', $this->admin, 'retry');
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $asset->stocktakes()->count());

        // Hasil 'found' langsung 'none' (tanpa penyesuaian).
        $found = $this->service->recordStocktake($asset, 2, 'found', $this->admin);
        $this->assertSame('none', $found->adjustment_status);
    }

    // ── 30.8 Penugasan & asuransi ────────────────────────────────────────

    public function test_check_out_and_check_in_are_mutually_exclusive(): void
    {
        $category = $this->category(AssetCategoryCode::Equipment->value);
        $user = User::factory()->create(['role' => 'customer']);
        $asset = $this->service->register([
            'name' => 'Proyektor Pinjam',
            'category_id' => $category->id,
            'acquisition_cost_idr' => 8_000_000,
        ], null, false);

        $assignment = $this->service->checkOut($asset, $user, $this->admin, 'Rapat', 'Baik');
        $this->assertSame('out', $assignment->status);

        // Check-out kedua ditolak (masih dipinjam).
        try {
            $this->service->checkOut($asset, $user, $this->admin, 'Lagi', 'Baik');
            $this->fail('Check-out kedua harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('peminjaman aktif', $e->getMessage());
        }

        // Check-in menutup pinjaman, check-in kedua ditolak.
        $this->service->checkIn($assignment, 'Kembali rusak kabel');
        $this->assertSame('returned', $assignment->fresh()->status);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->checkIn($assignment->fresh());
    }

    public function test_insurance_registers_policy_and_chain_event(): void
    {
        $category = $this->category(AssetCategoryCode::Vehicle->value);
        $asset = $this->service->register([
            'name' => 'Armada Asuransi',
            'category_id' => $category->id,
            'acquisition_cost_idr' => 300_000_000,
        ], null, false);

        $policy = $this->service->addInsurance($asset, 'POL-2026-001', 'Asuransi Uji', 300_000_000, 6_000_000, now()->toDateString(), now()->addYear()->toDateString());

        $this->assertSame('active', $policy->status);
        $this->assertSame(1, $asset->events()->where('event_type', AssetEventType::Insurance)->count());

        $this->assertTrue($this->service->verifyChain($asset->id)['valid']);
    }

    public function test_genesis_hash_length_does_not_exceed_column_capacity(): void
    {
        $this->assertLessThanOrEqual(64, strlen(AssetService::GENESIS_HASH));
    }
}
