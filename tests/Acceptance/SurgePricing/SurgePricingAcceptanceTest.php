<?php

declare(strict_types=1);

namespace Tests\Acceptance\SurgePricing;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Pricing\Application\Services\DynamicTickEngine;
use Modules\Pricing\Contracts\PriceLocker;
use Modules\Pricing\Domain\Models\PriceTick;
use Tests\TestCase;

class SurgePricingAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $pricingManager;

    protected User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        // Pastikan role pricing_manager tersedia
        $this->pricingManager = User::factory()->create(['role' => 'pricing_manager']);
        $this->unauthorizedUser = User::factory()->create(['role' => 'student']);
    }

    /** 1. Akses: role pricing_manager diizinkan, role lain 403 */
    public function test_access_control_for_pricing_surge_and_rejections(): void
    {
        // Pricing manager can access
        $this->actingAs($this->pricingManager)
            ->get(route('pricing.surge'))
            ->assertOk();

        $this->actingAs($this->pricingManager)
            ->get(route('pricing.rejections'))
            ->assertOk();

        // Unauthorized user receives 403
        $this->actingAs($this->unauthorizedUser)
            ->get(route('pricing.surge'))
            ->assertForbidden();

        $this->actingAs($this->unauthorizedUser)
            ->get(route('pricing.rejections'))
            ->assertForbidden();
    }

    /** 2. Alur utama: evaluasi vektor 1–5 D81-2 dan idempotensi slot 5 menit */
    public function test_dynamic_tick_calculation_vectors_and_slot_idempotency(): void
    {
        $engine = app(DynamicTickEngine::class);

        // Vektor 1: dasar 10.000, terakhir 10.000, faktor +1.500 +1.000 -> mentah 12.500 -> dibatasi 11.000
        $res1 = $engine->calculateTick([
            'sku' => 'SKU-V1',
            'base_price_idr' => 10_000,
            'last_price_idr' => 10_000,
            'floor_price_idr' => 8_000,
            'ceiling_price_idr' => 15_000,
            'het_price_idr' => 14_000,
            'price_step_idr' => 50,
            'factors' => ['f_stok' => 1500, 'f_permintaan' => 1000],
        ]);
        $this->assertSame(11_000, $res1['effective_price_idr']);
        $this->assertSame('dibatasi', $res1['status']);
        $this->assertTrue($res1['accepted']);

        // Vektor 4: dasar 10.333, terakhir 10.333, faktor +500 -> mentah 10.849,65 -> normal 10.850
        $res4 = $engine->calculateTick([
            'sku' => 'SKU-V4',
            'base_price_idr' => 10_333,
            'last_price_idr' => 10_333,
            'floor_price_idr' => 8_000,
            'ceiling_price_idr' => 15_000,
            'het_price_idr' => 14_000,
            'price_step_idr' => 50,
            'factors' => ['f_permintaan' => 500],
        ]);
        $this->assertSame(10_850, $res4['effective_price_idr']);
        $this->assertSame('normal', $res4['status']);
        $this->assertTrue($res4['accepted']);

        // Vektor 5: dasar 10.335, terakhir 10.335, faktor +300 -> mentah 10.645,05 -> kelipatan 50 -> 10.650
        $res5 = $engine->calculateTick([
            'sku' => 'SKU-V5',
            'base_price_idr' => 10_335,
            'last_price_idr' => 10_335,
            'floor_price_idr' => 8_000,
            'ceiling_price_idr' => 15_000,
            'het_price_idr' => 14_000,
            'price_step_idr' => 50,
            'factors' => ['f_permintaan' => 300],
        ]);
        $this->assertSame(10_650, $res5['effective_price_idr']);
        $this->assertSame('normal', $res5['status']);
        $this->assertTrue($res5['accepted']);

        // Test idempotensi slot 5 menit command pricing:tick
        $this->artisan('pricing:tick', ['--sku' => 'SKU-V1'])->assertSuccessful();
        $countAfterFirst = PriceTick::where('sku', 'SKU-V1')->count();
        $this->assertSame(1, $countAfterFirst);

        // Panggil kedua kali dalam slot yang sama -> tetap 1 tick
        $this->artisan('pricing:tick', ['--sku' => 'SKU-V1'])->assertSuccessful();
        $this->assertSame(1, PriceTick::where('sku', 'SKU-V1')->count());
    }

    /** 3. Guardrail keras: penolakan dicatat di pric_tick_rejections dan quote kedaluwarsa 409 */
    public function test_guardrail_rejections_and_expired_quote_409(): void
    {
        $engine = app(DynamicTickEngine::class);

        // Vektor 2: dasar 10.000, terakhir 8.500, faktor -3.000 -700 -> mentah 6.300 -> dibatasi 7.650 -> < floor (8.000) -> DITOLAK
        $res2 = $engine->calculateTick([
            'sku' => 'SKU-V2',
            'base_price_idr' => 10_000,
            'last_price_idr' => 8_500,
            'floor_price_idr' => 8_000,
            'ceiling_price_idr' => 15_000,
            'het_price_idr' => 14_000,
            'price_step_idr' => 50,
            'factors' => ['f_umur_simpan' => -3000, 'f_stok' => -700],
        ]);
        $this->assertFalse($res2['accepted']);
        $this->assertSame(8_500, $res2['effective_price_idr']);
        $this->assertSame('BELOW_FLOOR', $res2['rejection_reason']);
        $this->assertDatabaseHas('pric_tick_rejections', [
            'sku' => 'SKU-V2',
            'rejection_reason' => 'BELOW_FLOOR',
        ]);

        // Vektor 3: dasar 13.000, terakhir 13.500, faktor +1.500 +1.000 -> mentah 16.250 -> dibatasi 14.850 -> > HET (14.000) -> DITOLAK
        $res3 = $engine->calculateTick([
            'sku' => 'SKU-V3',
            'base_price_idr' => 13_000,
            'last_price_idr' => 13_500,
            'floor_price_idr' => 8_000,
            'ceiling_price_idr' => 15_000,
            'het_price_idr' => 14_000,
            'price_step_idr' => 50,
            'factors' => ['f_stok' => 1500, 'f_permintaan' => 1000],
        ]);
        $this->assertFalse($res3['accepted']);
        $this->assertSame(13_500, $res3['effective_price_idr']);
        $this->assertSame('ABOVE_HET', $res3['rejection_reason']);
        $this->assertDatabaseHas('pric_tick_rejections', [
            'sku' => 'SKU-V3',
            'rejection_reason' => 'ABOVE_HET',
        ]);

        // Quote kedaluwarsa saat checkout -> 409 + harga baru
        $expiredQuoteCode = 'QT-EXP-CHECKOUT';
        DB::table('pric_frozen_quotes')->insert([
            'quote_code' => $expiredQuoteCode,
            'sku' => 'SKU-EXP',
            'frozen_price_idr' => 10_000,
            'floor_price_idr' => 8_000,
            'ceiling_price_idr' => 14_000,
            'quote_hash' => hash('sha256', 'dummy'),
            'status' => 'LOCKED',
            'locked_until' => now()->subMinutes(16),
            'created_at' => now()->subMinutes(16),
            'updated_at' => now()->subMinutes(16),
        ]);

        $response = $this->actingAs($this->pricingManager)
            ->postJson(route('pricing.checkout-quote'), [
                'quote_code' => $expiredQuoteCode,
                'order_ref' => 'ORD-EXP-001',
            ]);

        $response->assertStatus(409)
            ->assertJsonStructure(['error', 'new_quote']);
    }

    /** 4. Dokumen order lama tidak berubah harga setelah ada tick baru */
    public function test_order_price_immutable_against_subsequent_ticks(): void
    {
        $locker = app(PriceLocker::class);
        $orderId = 'ORD-IMMUTABLE-01';
        $sku = 'SKU-LOCKED-01';

        // Kunci harga order pada 10.000 saat awal
        $locker->lockLine('order', $orderId, [
            'sku' => $sku,
            'qty' => 2.0,
            'price_idr' => 10_000,
        ], 'price_tick');

        // Tick baru melonjak ke 12.000
        $engine = app(DynamicTickEngine::class);
        $engine->calculateAndPersistTick($sku, 12_000, 'normal');

        // Pastikan harga terkunci di order tetap 10.000
        $locked = DB::table('pric_price_locks')
            ->where('subject_type', 'order')
            ->where('subject_id', $orderId)
            ->where('sku', $sku)
            ->first();

        $this->assertNotNull($locked);
        $this->assertSame(10_000, (int) $locked->applied_price_idr);
    }

    /** 5. Jalur galat redirect + pesan (bukan 500) dan proyeksi elastisitas item 64.1 */
    public function test_error_handling_and_elasticity_projection(): void
    {
        // Jalur galat: input tidak valid -> redirect + pesan, bukan 500
        $badResponse = $this->actingAs($this->pricingManager)
            ->post(route('pricing.surge.calculate'), [
                'sku' => '', // kosong memicu validasi galat
            ]);

        $badResponse->assertRedirect();
        $badResponse->assertSessionHas('error');

        // Proyeksi elastisitas D81-3: q' = round(q * (10000 - e * dp_bps / 10000) / 10000)
        $engine = app(DynamicTickEngine::class);
        // baseline q = 1000, e = 15.000 bps (1.5 elastis), delta_p = +1.000 bps (+10%)
        // q' = 1000 * (10000 - 15000 * 1000 / 10000) / 10000 = 1000 * (10000 - 1500) / 10000 = 850
        $projected = $engine->projectDemandWithElasticity(
            baseQuantity: 1000,
            elasticityBps: 15_000,
            deltaPriceBps: 1_000
        );
        $this->assertSame(850, $projected);
    }

    /** 6. Audit dua sumber: data bersih exit 0, selisih baris order exit != 0 */
    public function test_pricing_audit_command_with_clean_and_corrupt_data(): void
    {
        // Kondisi bersih -> exit 0
        $this->artisan('pricing:audit')->assertSuccessful();

        // Korupsi: buat mismatch baris order vs harga terkunci
        $corruptLockId = DB::table('pric_price_locks')->insertGetId([
            'id' => (string) Str::uuid(),
            'subject_type' => 'order',
            'subject_id' => 'ORD-CORRUPT-TEST',
            'sku' => 'SKU-CORRUPT-01',
            'qty' => 1,
            'list_price_idr' => 10_000,
            'applied_price_idr' => 10_000,
            'discount_idr' => 0,
            'source_kind' => 'manual',
            'locked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simulasikan ada order item dengan harga berbeda dari yang dikunci
        DB::table('pric_frozen_quotes')->insert([
            'quote_code' => 'QT-MISMATCH-TEST',
            'sku' => 'SKU-CORRUPT-01',
            'frozen_price_idr' => 15_000, // selisih dengan applied_price_idr 10.000
            'floor_price_idr' => 8_000,
            'ceiling_price_idr' => 20_000,
            'quote_hash' => 'hash_corrupt',
            'order_ref' => 'ORD-CORRUPT-TEST',
            'status' => 'CONVERTED',
            'locked_until' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Audit harus menangkap selisih dan exit != 0
        $this->artisan('pricing:audit')
            ->assertFailed()
            ->expectsOutputToContain('order.price_mismatch');

        // Bersihkan korupsi dan pastikan bersih kembali
        DB::table('pric_frozen_quotes')->where('quote_code', 'QT-MISMATCH-TEST')->delete();
        DB::table('pric_price_locks')->where('subject_id', 'ORD-CORRUPT-TEST')->delete();

        $this->artisan('pricing:audit')->assertSuccessful();
    }
}
