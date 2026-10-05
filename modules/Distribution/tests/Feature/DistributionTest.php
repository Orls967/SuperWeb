<?php

declare(strict_types=1);

namespace Tests\Feature\Distribution;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Distribution\Application\Services\DistributionService;
use Modules\Distribution\Domain\Models\Distributor;
use Tests\TestCase;

/**
 * Regresi Fase 42: hirarki & jenis jaminan onboarding, teritori eksklusif
 * + konflik, piutang/AR ledger + denda + blokir otomatis, target/tier,
 * outlet coverage, scorecard, dist:audit.
 */
class DistributionTest extends TestCase
{
    use RefreshDatabase;

    private DistributionService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->service = app(DistributionService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    private function makeApproved(int $limit = 100_000_000): Distributor
    {
        $d = $this->service->registerDistributor([
            'code' => 'DST-'.uniqid(),
            'name' => 'Distributor uji',
            'credit_limit_idr' => $limit,
            'payment_terms_days' => 30,
        ]);
        $this->service->recordSecurity($d, ['kind' => 'deposit', 'amount_idr' => 5_000_000]);
        $this->service->submitOnboarding($d, $this->admin);

        $reviewer = User::factory()->create(['role' => 'admin']);

        return $this->service->approveOnboarding($d, $reviewer, $limit);
    }

    // ── 42.1 Hirarki ─────────────────────────────────────────────────────

    public function test_hierarchy_and_junior_requires_approved_parent(): void
    {
        $d1 = $this->makeApproved();

        $sub = $this->service->registerDistributor([
            'code' => 'SUB-01', 'name' => 'Sub', 'kind' => 'sub_distributor', 'parent_id' => $d1->id,
        ]);
        $this->assertSame($d1->id, $sub->parent_id);
        $this->assertSame('onboarding', $sub->status);

        // Parent belum approved → ditolak.
        $onb = $this->service->registerDistributor(['code' => 'ONB-01', 'name' => 'Belum']);
        try {
            $this->service->registerDistributor([
                'code' => 'SUB-02', 'name' => 'Sub2', 'parent_id' => $onb->id,
            ]);
            $this->fail('Induk onboarding tidak boleh punya anak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('approved', $e->getMessage());
        }
    }

    // ── 42.3 Onboarding four-eyes + jaminan ──────────────────────────────

    public function test_onboarding_requires_security_and_four_eyes(): void
    {
        $d = $this->service->registerDistributor(['code' => 'NOSEC', 'name' => 'Tanpa jaminan']);

        try {
            $this->service->submitOnboarding($d, $this->admin);
            $this->fail('Onboarding tanpa jaminan harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('jaminan', $e->getMessage());
        }

        $this->service->recordSecurity($d, ['kind' => 'bank_guarantee', 'amount_idr' => 10_000_000, 'reference' => 'BG-1']);
        $this->service->submitOnboarding($d, $this->admin);
        $this->assertNotNull($d->fresh()->approval_id);

        // Four-eyes: pengaju tidak boleh menyetujui.
        try {
            $this->service->approveOnboarding($d, $this->admin, 50_000_000);
            $this->fail('Four-eyes dilanggar.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Four-Eyes', $e->getMessage());
        }

        $reviewer = User::factory()->create(['role' => 'admin']);
        $approved = $this->service->approveOnboarding($d, $reviewer, 50_000_000);
        $this->assertSame('approved', $approved->status);
        $this->assertSame(50_000_000, (int) $approved->credit_limit_idr);
        $this->assertNotNull($approved->approved_at);
    }

    // ── 42.2 Teritori & konflik eksklusif ────────────────────────────────

    public function test_territory_hierarchy_and_exclusive_conflict(): void
    {
        $province = $this->service->createTerritory(['code' => 'KAL-SEL', 'name' => 'Kalimantan Selatan', 'level' => 'province']);
        $city = $this->service->createTerritory(['code' => 'BJM', 'name' => 'Banjarmasin', 'level' => 'city', 'parent_id' => $province->id]);

        // Level salah: city tanpa induk ditolak.
        try {
            $this->service->createTerritory(['code' => 'BAD', 'name' => 'Salah', 'level' => 'city']);
            $this->fail('City tanpa induk harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('induk', $e->getMessage());
        }

        $a = $this->makeApproved();
        $b = $this->makeApproved();

        $this->service->coverTerritory($a, $city, true, now()->toDateString());

        try {
            $this->service->coverTerritory($b, $city, true, now()->toDateString());
            $this->fail('Konflik eksklusif harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Konflik teritori', $e->getMessage());
        }

        // Non-eksklusif di wilayah yang sama diperbolehkan.
        $result = $this->service->coverTerritory($b, $city, false, now()->toDateString());
        $this->assertFalse($result['coverage']->exclusive);
        $this->assertEmpty($this->service->territoryConflicts());
    }

    // ── 42.4 Piutang, ledger, denda, blokir ──────────────────────────────

    public function test_invoice_payment_updates_exposure_and_ledger(): void
    {
        $d = $this->makeApproved(limit: 20_000_000);

        $invoice = $this->service->issueInvoice(
            $d, 10_000_000, now()->toDateString(), now()->addDays(30)->toDateString(), $this->admin
        );
        $this->assertSame('open', $invoice->status);
        $this->assertSame(10_000_000, (int) $d->fresh()->credit_exposure_idr);
        $this->artisan('bank:reconcile')->assertSuccessful();

        // Bayar sebagian.
        $partial = $this->service->payInvoice($invoice, 4_000_000, now()->toDateString(), $this->admin);
        $this->assertSame('partial', $partial->status);
        $this->assertSame(6_000_000, (int) $d->fresh()->credit_exposure_idr);
        $this->artisan('bank:reconcile')->assertSuccessful();

        // Bayar melebihi sisa ditolak.
        try {
            $this->service->payInvoice($partial, 7_000_000, now()->toDateString(), $this->admin);
            $this->fail('Pembayaran melebihi sisa harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('melebihi sisa', $e->getMessage());
        }

        $paid = $this->service->payInvoice($partial, 6_000_000, now()->toDateString(), $this->admin);
        $this->assertSame('paid', $paid->status);
        $this->assertSame(0, (int) $d->fresh()->credit_exposure_idr);
        $this->artisan('bank:reconcile')->assertSuccessful();
        $this->artisan('dist:audit')->assertSuccessful();
    }

    public function test_over_limit_blocks_and_payment_unblocks(): void
    {
        $d = $this->makeApproved(limit: 5_000_000);

        // 6jt > 5jt → blokir otomatis saat terbit.
        $invoice = $this->service->issueInvoice(
            $d, 6_000_000, now()->toDateString(), now()->addDays(30)->toDateString(), $this->admin
        );
        $this->assertSame('blocked', $d->fresh()->status);
        $this->assertTrue($d->fresh()->isBlocked());

        // Diblokir → tagihan baru ditolak.
        try {
            $this->service->issueInvoice($d, 1_000_000, now()->toDateString(), now()->addDays(10)->toDateString(), $this->admin);
            $this->fail('Distributor blokir tidak boleh diterbitkan tagihan.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('approved', $e->getMessage());
        }

        // Bayar lunas → eksposur 0 → keluar blokir.
        $this->service->payInvoice($invoice, 6_000_000, now()->toDateString(), $this->admin);
        $fresh = $d->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertFalse($fresh->isBlocked());
        $this->artisan('dist:audit')->assertSuccessful();
    }

    public function test_late_fee_and_aging(): void
    {
        $d = $this->makeApproved();
        $invoice = $this->service->issueInvoice(
            $d, 1_000_000, now()->subDays(40)->toDateString(), now()->subDays(10)->toDateString(), $this->admin
        );

        $count = $this->service->sweepOverdue();
        $this->assertGreaterThanOrEqual(1, $count);

        $invoice = $invoice->fresh();
        $this->assertSame('overdue', $invoice->status);
        $this->assertGreaterThan(0, (int) $invoice->denda_idr, 'Denda keterlambatan diterapkan.');
        // Cap 5% dari 1jt = 50.000.
        $this->assertLessThanOrEqual(50_000, (int) $invoice->denda_idr);

        $aging = $this->service->agingReport($d);
        $this->assertArrayHasKey('0-30', $aging);
        $this->assertArrayHasKey('31-60', $aging);
        $this->assertSame($invoice->openAmount(), array_sum($aging));
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_overdue_beyond_grace_blocks_distributor(): void
    {
        $d = $this->makeApproved();
        // Terlambat 10 hari (> grace 7).
        $this->service->issueInvoice(
            $d, 1_000_000, now()->subDays(45)->toDateString(), now()->subDays(10)->toDateString(), $this->admin
        );
        $this->service->sweepOverdue();

        $this->assertSame('blocked', $d->fresh()->status, 'Overdue > 7 hari memblokir otomatis.');
    }

    // ── 42.5 Target & tier ───────────────────────────────────────────────

    public function test_target_achievement_and_tier_evaluation(): void
    {
        $d = $this->makeApproved();
        $period = now()->format('Y');

        $target = $this->service->setTarget($d, [
            'product_sku' => 'SKU-1', 'period' => $period, 'target_qty' => 100, 'basis' => 'sell_in',
        ]);
        $this->service->recordAchievement($target, 95);
        $this->service->recordAchievement($target, 5); // akumulatif
        $this->assertEqualsWithDelta(100.0, (float) $target->fresh()->achieved_qty, 0.0001);

        // Skor ≥ gold threshold → tier naik.
        $result = $this->service->evaluateTier($d->fresh());
        $this->assertSame('gold', $result['tier']);
        $this->assertTrue($result['changed']);
        $this->assertSame('gold', $d->fresh()->tier);

        // Diskon tier baca dari tabel dist_tiers.
        $this->assertEqualsWithDelta(5.0, $this->service->tierDiscountPercent('gold'), 0.01);
        $this->assertEqualsWithDelta(0.0, $this->service->tierDiscountPercent('bronze'), 0.01);
    }

    // ── 42.7 Outlet ──────────────────────────────────────────────────────

    public function test_outlet_requires_coverage(): void
    {
        $d = $this->makeApproved();
        $province = $this->service->createTerritory(['code' => 'KAL-SUM', 'name' => 'Kalimantan Tengah', 'level' => 'province']);

        try {
            $this->service->addOutlet($d, ['code' => 'OTL-1', 'name' => 'Toko X', 'territory_id' => $province->id]);
            $this->fail('Outlet di wilayah tak ter-cover harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('belum dicover', $e->getMessage());
        }

        $this->service->coverTerritory($d, $province, false, now()->toDateString());
        $outlet = $this->service->addOutlet($d, [
            'code' => 'otl-1', 'name' => 'Toko X', 'segment' => 'horeca', 'territory_id' => $province->id,
        ]);
        $this->assertSame('OTL-1', $outlet->code);

        // Kode ganda per distributor ditolak.
        try {
            $this->service->addOutlet($d, ['code' => 'OTL-1', 'name' => 'Duplikat']);
            $this->fail('Kode outlet ganda harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('sudah dipakai', $e->getMessage());
        }
    }

    // ── 42.8 Scorecard ───────────────────────────────────────────────────

    public function test_scorecard_computes_dso_and_recommends_tier(): void
    {
        $d = $this->makeApproved();
        $period = now()->format('Y');
        $this->service->setTarget($d, ['product_sku' => 'SKU-A', 'period' => $period, 'target_qty' => 10, 'basis' => 'sell_in']);
        $this->service->recordAchievement($d->targets()->first(), 9);

        $scorecard = $this->service->computeScorecard($d, $period, [
            'fill_rate_percent' => 95.0, 'price_compliance_percent' => 98.0,
        ]);

        $this->assertSame($d->id, $scorecard->distributor_id);
        $this->assertEqualsWithDelta(90.0, (float) $scorecard->achievement_percent, 0.5);
        $this->assertGreaterThan(0, (float) $scorecard->score);
        $this->assertLessThanOrEqual(100.0, (float) $scorecard->score);
        $this->assertContains($scorecard->recommended_tier, ['bronze', 'silver', 'gold']);
        $this->assertSame(1, Distributor::find($d->id)->scorecards()->count());
    }

    // ── 42.6 Portal distributor ─────────────────────────────────────────

    public function test_distributor_portal_renders_for_linked_user(): void
    {
        $d = $this->makeApproved();
        $d->update(['owner_user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->get(route('distribution.portal.home'))
            ->assertOk()
            ->assertSee('Portal Distributor');
    }

    public function test_portal_forbidden_without_link(): void
    {
        $this->actingAs($this->admin)
            ->get(route('distribution.portal.home'))
            ->assertForbidden();
    }

    // ── 42.9 Audit ───────────────────────────────────────────────────────

    public function test_dist_audit_passes_and_route_renders(): void
    {
        $d = $this->makeApproved();
        $this->service->issueInvoice($d, 2_000_000, now()->toDateString(), now()->addDays(15)->toDateString(), $this->admin);

        $this->artisan('dist:audit')->assertSuccessful();

        $this->actingAs($this->admin)
            ->get(route('distribution.index'))
            ->assertOk()
            ->assertSee('Jaringan Distributor');
    }
}
