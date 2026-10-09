<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Pricing\Application\Services\PricingService;
use Modules\Pricing\Domain\Models\AnalyticsSnapshot;
use Modules\Pricing\Domain\Models\DiscountRule;
use Modules\Pricing\Domain\Models\MarginPolicy;
use Modules\Pricing\Domain\Models\PriceLock;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Events\OrderPaid;
use Modules\Store\Domain\Models\Category;
use Modules\Store\Domain\Models\Order;
use Modules\Store\Domain\Models\OrderItem;
use Modules\Store\Domain\Models\Product;
use Tests\TestCase;

/**
 * Regresi Fase 44: price list tanpa overlap + prioritas, waterfall diskon
 * deterministik + kupon, promo klaim four-eyes + anggaran, price lock
 * immutable, margin floor + override four-eyes, analitik leakage, pricing:audit.
 */
class PricingEngineTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $pricing;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->pricing = app(PricingService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    // ── 44.1 Price list: overlap & prioritas ─────────────────────────────

    public function test_price_list_activation_blocks_overlap_and_resolves_priority(): void
    {
        $first = $this->pricing->createPriceList([
            'code' => 'PL-1', 'name' => 'Umum', 'channel' => 'general',
            'valid_from' => now()->toDateString(), 'valid_until' => now()->addMonth()->toDateString(),
            'priority' => 100,
        ], $this->admin);
        $this->pricing->setPriceListItem($first, 'SKU-A', 10_000);
        $this->pricing->activatePriceList($first);
        $this->assertSame('active', $first->fresh()->status);

        // Periode sama + channel sama → overlap ditolak.
        $second = $this->pricing->createPriceList([
            'code' => 'PL-2', 'name' => 'Umum 2', 'channel' => 'general',
            'valid_from' => now()->addDays(5)->toDateString(), 'valid_until' => now()->addDays(40)->toDateString(),
            'priority' => 90,
        ], $this->admin);
        $this->pricing->setPriceListItem($second, 'SKU-A', 12_000);
        try {
            $this->pricing->activatePriceList($second);
            $this->fail('Overlap price list harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('overlap', $e->getMessage());
        }

        // Segment berbeda → tidak overlap, aktif + menang (lebih spesifik).
        $segmented = $this->pricing->createPriceList([
            'code' => 'PL-SEG', 'name' => 'Horeca', 'channel' => 'general', 'segment' => 'horeca',
            'valid_from' => now()->toDateString(), 'priority' => 200,
        ], $this->admin);
        $this->pricing->setPriceListItem($segmented, 'SKU-A', 9_500);
        $this->pricing->activatePriceList($segmented);

        $resolved = $this->pricing->resolveList('SKU-A', 'general', 'horeca');
        $this->assertSame('PL-SEG', $resolved?->code, 'Segmen mengalahkan general.');
        $this->assertSame(9_500, $this->pricing->resolveListPrice($resolved, 'SKU-A', 1));

        // Price list aktif immutable.
        try {
            $this->pricing->setPriceListItem($first->fresh(), 'SKU-A', 11_111);
            $this->fail('Price list aktif harus immutable.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }
    }

    // ── 44.2 Waterfall diskon deterministik ──────────────────────────────

    public function test_discount_waterfall_is_deterministic_and_auditable(): void
    {
        // Volume 5% untuk qty ≥10, lalu kupon 10% (order 20, non-stackable tidak dipakai).
        DiscountRule::create([
            'code' => 'VOL-10', 'name' => 'Volume 10+', 'kind' => 'volume', 'channel' => 'general',
            'threshold_qty' => 10, 'percent_off' => 5, 'order' => 10, 'stackable' => true,
            'valid_from' => now()->subDay()->toDateString(), 'is_active' => true,
        ]);
        DiscountRule::create([
            'code' => 'CPN-10', 'name' => 'Kupon 10%', 'kind' => 'coupon', 'channel' => 'general',
            'percent_off' => 10, 'order' => 90, 'coupon_code' => 'HEMAT10', 'stackable' => true,
            'valid_from' => now()->subDay()->toDateString(), 'is_active' => true,
        ]);

        $lines = [['sku' => 'SKU-A', 'qty' => 20, 'price_idr' => 1_000]];
        $result = $this->pricing->calculateWaterfall($lines, 'general', null, 'HEMAT10');

        $this->assertSame(20_000, $result['list_total']);
        // Volume 5% × 20.000 = 1.000; kupon 10% × 19.000 = 1.900 → total 2.900.
        $this->assertSame(2_900, $result['discount_total']);
        $this->assertSame(17_100, $result['net_total']);
        $this->assertCount(2, $result['waterfall']);
        $this->assertSame('VOL-10', $result['waterfall'][0]['code']);
        $this->assertSame('CPN-10', $result['waterfall'][1]['code']);

        // Urutan deterministik: jalankan ulang → hasil identik.
        $again = $this->pricing->calculateWaterfall($lines, 'general', null, 'HEMAT10');
        $this->assertSame($result['net_total'], $again['net_total']);

        // Kupon tidak valid ditolak.
        try {
            $this->pricing->calculateWaterfall($lines, 'general', null, 'SALAH');
            $this->fail('Kupon salah harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Kupon', $e->getMessage());
        }
    }

    // ── 44.3 Promo klaim four-eyes + anggaran ────────────────────────────

    public function test_promotion_claim_validates_settles_and_respects_budget(): void
    {
        $promotion = $this->pricing->createPromotion([
            'code' => 'PR-1', 'name' => 'Promo Endok', 'mechanic' => 'bill_back',
            'budget_idr' => 100_000, 'percent_off' => 5,
            'valid_from' => now()->subDay()->toDateString(), 'valid_until' => now()->addMonth()->toDateString(),
        ]);

        // Klaim tanpa bukti ditolak.
        try {
            $this->pricing->submitPromotionClaim($promotion, null, 'ORD-1', 10_000, $this->admin, '  ');
            $this->fail('Bukti wajib.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Bukti', $e->getMessage());
        }

        $claim = $this->pricing->submitPromotionClaim($promotion, null, 'ORD-1', 60_000, $this->admin, 'Faktur terlampir');
        $this->assertSame('submitted', $claim->status);

        // Melebihi sisa anggaran ditolak.
        try {
            $this->pricing->submitPromotionClaim($promotion, null, 'ORD-2', 60_000, $this->admin, 'Bukti lain');
            $this->fail('Melebihi anggaran harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('anggaran', $e->getMessage());
        }

        $validated = $this->pricing->approvePromotionClaim($claim, $this->admin);
        $this->assertSame('validated', $validated->status);
        $this->assertNotNull($validated->approval_id);

        // Settlement: four-eyes (pemohon ≠ approver).
        try {
            $this->pricing->settlePromotionClaim($validated, $this->admin);
            $this->fail('Four-eyes dilanggar.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Four-Eyes', $e->getMessage());
        }

        $reviewer = User::factory()->create(['role' => 'admin']);
        $settled = $this->pricing->settlePromotionClaim($validated, $reviewer);
        $this->assertSame('settled', $settled->status);
        $this->assertSame(60_000, (int) $promotion->fresh()->spent_idr);
        $this->assertSame(40_000, $promotion->fresh()->remainingBudget());
    }

    // ── 44.4 Price lock immutable ────────────────────────────────────────

    public function test_price_lock_is_immutable_on_replay(): void
    {
        $lock = $this->pricing->lockPrice(
            'dist_order', 'ORD-LOCK-1', 'SKU-A', 5,
            10_000, 9_000, 1_000, null, 'discount', null, [['step' => 'rule']], 'promo'
        );

        // Replay dengan data beda → kunci lama dipakai (immutable).
        $replay = $this->pricing->lockPrice(
            'dist_order', 'ORD-LOCK-1', 'SKU-A', 5,
            10_000, 10_000, 0, null, 'price_list', null, [], null
        );

        $this->assertSame($lock->id, $replay->id);
        $this->assertSame(9_000, (int) $replay->applied_price_idr, 'Snapshot awal tidak ditimpa.');
        $this->assertSame(1, PriceLock::where('subject_id', 'ORD-LOCK-1')->count());
    }

    // ── 44.5 Margin floor + override four-eyes ──────────────────────────

    public function test_margin_floor_blocks_price_until_override_approved(): void
    {
        MarginPolicy::create([
            'channel' => 'general', 'sku' => 'SKU-B', 'min_margin_percent' => 20,
            'floor_cost_idr' => 8_000, 'is_active' => true,
        ]);

        $list = $this->pricing->createPriceList([
            'code' => 'PL-B', 'name' => 'B', 'channel' => 'general',
            'valid_from' => now()->toDateString(),
        ], $this->admin);
        $this->pricing->setPriceListItem($list, 'SKU-B', 9_000); // floor 9.600 → terlalu rendah
        $this->pricing->activatePriceList($list);

        try {
            $this->pricing->quote('SKU-B', 1, 'general');
            $this->fail('Harga di bawah floor harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('floor', $e->getMessage());
        }

        // Ajukan override 9.000 → approval four-eyes → quote lolos.
        $override = $this->pricing->requestOverride('SKU-B', 'general', 9_000, 'Kompetisi harga lokal', $this->admin);
        $this->assertSame('pending', $override->status);

        try {
            $this->pricing->decideOverride($override, $this->admin, true);
            $this->fail('Four-eyes dilanggar.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Four-Eyes', $e->getMessage());
        }

        $reviewer = User::factory()->create(['role' => 'admin']);
        $approved = $this->pricing->decideOverride($override, $reviewer, true, 'Disetujui kompetisi');
        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->decided_at);

        $quote = $this->pricing->quote('SKU-B', 1, 'general');
        $this->assertSame(9_000, $quote['price_idr']);
        $this->assertSame('override', $quote['source_kind']);

        // Lock harga (44.4) saat quote dokumen.
        $quote2 = $this->pricing->quote('SKU-B', 2, 'general', null, null, null, null, 'dist_order', 'ORD-B-1');
        $lock = PriceLock::where('subject_type', 'dist_order')->where('subject_id', 'ORD-B-1')->firstOrFail();
        $this->assertNotNull($lock);
        $this->assertSame('override', $lock->source_kind);
        unset($quote2);
    }

    // ── 44.6 Store price lock event ─────────────────────────────────────

    public function test_store_order_paid_locks_line_prices_idempotently(): void
    {
        $category = Category::create(['name' => 'Pricing', 'slug' => 'pr-'.uniqid()]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Produk Pricing', 'slug' => 'pp-'.uniqid(),
            'sku' => 'SKU-STORE-1', 'price' => 25_000, 'cached_stock' => 5, 'is_car' => false,
        ]);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::create([
            'uuid' => (string) Str::uuid(), 'number' => 'ORD-PR-1',
            'user_id' => $customer->id, 'status' => OrderStatus::PAID,
            'subtotal' => 25_000, 'shipping_fee' => 0, 'discount' => 0, 'grand_total' => 25_000,
            'shipping_address' => ['street' => 'Jl. Uji', 'city' => 'Banjarmasin'],
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'name_snapshot' => $product->name, 'price_snapshot' => 25_000, 'qty' => 1, 'line_total' => 25_000,
        ]);

        event(new OrderPaid($order->id, $customer->id, 25_000));
        $locks = PriceLock::where('subject_type', 'store_order')->where('subject_id', $order->id)->get();
        $this->assertCount(1, $locks);
        $this->assertSame('SKU-STORE-1', $locks->first()->sku);

        // Replay idempoten.
        event(new OrderPaid($order->id, $customer->id, 25_000));
        $this->assertSame(1, PriceLock::where('subject_type', 'store_order')->where('subject_id', $order->id)->count());
    }

    // ── 44.7 Analitik & pricing:audit ───────────────────────────────────

    public function test_analytics_snapshot_and_audit_command(): void
    {
        $this->pricing->lockPrice('dist_order', 'AN-1', 'SKU-A', 10, 1_000, 900, 1_000, null, 'discount', null);
        $this->pricing->lockPrice('dist_order', 'AN-2', 'SKU-A', 5, 1_000, 1_000, 0, null, 'price_list', null);

        $snapshot = $this->pricing->computeAnalytics(now()->format('Y-m'), 'general');
        $this->assertSame(14_000, (int) $snapshot->volume_idr); // 10×900 + 5×1000
        $this->assertLessThan(100.0, (float) $snapshot->realized_vs_list_percent);
        $this->assertSame(0, (int) $snapshot->discount_leakage_idr, 'Diskon via rule tidak dihitung leakage.');
        $this->assertSame(1, AnalyticsSnapshot::count());

        $this->artisan('pricing:audit')->assertSuccessful();

        $this->actingAs($this->admin)
            ->get(route('pricing.index'))
            ->assertOk()
            ->assertSee('Harga, Promo');
    }
}
