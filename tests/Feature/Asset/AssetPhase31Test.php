<?php

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Asset\Application\Services\AssetAuditService;
use Modules\Asset\Application\Services\AssetService;
use Modules\Asset\Application\Services\AssetTcoService;
use Modules\Asset\Application\Services\DepreciationService;
use Modules\Asset\Application\Services\LeaseService;
use Modules\Asset\Application\Services\RevaluationService;
use Modules\Asset\Application\Services\WorkOrderService;
use Modules\Asset\Domain\Enums\AssetCategoryCode;
use Modules\Asset\Domain\Enums\AssetStatus;
use Modules\Asset\Domain\Enums\DepreciationMethod;
use Modules\Asset\Domain\Enums\DisposalMethod;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetCategory;
use Modules\Asset\Domain\Models\AssetUsageLog;
use Modules\Asset\Domain\Models\Depreciation;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Core\Application\Services\SystemHealthService;
use Modules\Core\Domain\Models\ApprovalRequest;
use Tests\TestCase;

/**
 * Regresi Fase 31: penyusutan (metode + dua buku), approval revaluasi,
 * disposal, work order (expense/capitalized), sewa amortisasi, TCO, ast:audit.
 */
class AssetPhase31Test extends TestCase
{
    use RefreshDatabase;

    private AssetService $assets;

    private DepreciationService $depreciation;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
        $this->assets = app(AssetService::class);
        $this->assets->ensureDefaultCategories();
        $this->depreciation = app(DepreciationService::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function category(string $code): AssetCategory
    {
        return AssetCategory::where('code', $code)->firstOrFail();
    }

    private function makeAsset(array $extra = []): Asset
    {
        $category = $this->category($extra['category_code'] ?? AssetCategoryCode::Equipment->value);

        // postLedger=true: perolehan wajib ikut tercatat ke ast:fixed_assets,
        // karena ast:audit membandingkan ledger vs book value register.
        return $this->assets->register([
            'name' => $extra['name'] ?? 'Aset Fase 31',
            'category_id' => $category->id,
            'acquisition_cost_idr' => $extra['acquisition_cost_idr'] ?? 120_000_000,
            'landed_cost_idr' => $extra['landed_cost_idr'] ?? 0,
            'acquired_at' => $extra['acquired_at'] ?? now()->subMonths(12)->toDateString(),
        ], null, true);
    }

    // ── 31.1 / 31.2 Penyusutan ──────────────────────────────────────────

    public function test_straight_line_depreciation_is_monthly_and_idempotent(): void
    {
        $asset = $this->makeAsset(); // 120jt, kategori equipment 8th declining → paksa straight line

        $first = $this->depreciation->depreciate($asset, '2026-09', DepreciationMethod::StraightLine);
        // 120.000.000 / (8 tahun × 12) = 1.250.000/bulan
        $this->assertSame(1_250_000, $first['amount_idr']);
        $this->assertSame(120_000_000 - 1_250_000, $first['book_value_after_idr']);

        // Idempoten: pemanggilan ulang periode sama tidak menggandakan.
        $again = $this->depreciation->depreciate($asset, '2026-09', DepreciationMethod::StraightLine);
        $this->assertSame($first['amount_idr'], $again['amount_idr']);
        $this->assertSame(1, Depreciation::where('asset_id', $asset->id)->where('period', '2026-09')->count());

        // Ledger tercatat sekali, kunci deterministik.
        $this->assertSame(1, LedgerTransaction::where('idempotency_key', 'ast:depreciate:'.$asset->id.':2026-09:commercial')->count());
        $this->assertSame(1_250_000, (int) LedgerAccount::where('code', 'ast:depreciation_expense')->first()->money()->amount->toInt());

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_declining_balance_and_units_of_production_methods(): void
    {
        // Saldo menurun ganda: aset bangunan 20 th → laju bulanan 2/(20*12).
        $building = $this->makeAsset([
            'name' => 'Bangunan Gudang',
            'category_code' => AssetCategoryCode::Building->value,
            'acquisition_cost_idr' => 1_000_000_000,
        ]);

        $db = $this->depreciation->depreciate($building, '2026-09', DepreciationMethod::DecliningBalance);
        // Laju saldo menurun ganda tahunan = 2/life = 2/20 = 10%/th;
        // per bulan ≈ 0,833333% → 1.000.000.000 × 0,0083333 = 8.333.333.
        $this->assertSame(8_333_333, $db['amount_idr']);

        // Unit produksi: 25.000 dari total 100.000 unit → 25% dari 120jt = 30jt.
        $vehicle = $this->makeAsset([
            'name' => 'Truk Unit Produksi',
            'category_code' => AssetCategoryCode::Vehicle->value,
            'acquisition_cost_idr' => 120_000_000,
        ]);

        $uop = $this->depreciation->depreciate($vehicle, '2026-09', DepreciationMethod::UnitsOfProduction, 'commercial', 25_000, 100_000);
        $this->assertSame(30_000_000, $uop['amount_idr']);
    }

    public function test_fiscal_book_is_tracked_separately_from_commercial(): void
    {
        $asset = $this->makeAsset();

        $commercial = $this->depreciation->depreciate($asset, '2026-09', DepreciationMethod::StraightLine, 'commercial');
        $fiscal = $this->depreciation->depreciate($asset, '2026-09', DepreciationMethod::StraightLine, 'fiscal');

        // Buku fiskal tidak mengubah book value aset (dua buku).
        $this->assertSame($commercial['amount_idr'], $fiscal['amount_idr']);
        $this->assertSame(120_000_000 - 1_250_000, $asset->fresh()->book_value_idr);
        $this->assertSame(1_250_000, (int) $asset->fresh()->accumulated_depreciation_idr);

        // Rekaman terpisah per buku.
        $this->assertSame(2, Depreciation::where('asset_id', $asset->id)->where('period', '2026-09')->count());
        $this->assertNotNull(LedgerAccount::where('code', 'ast:fiscal_depreciation_expense')->first());
    }

    public function test_land_and_disposed_assets_do_not_depreciate(): void
    {
        $land = $this->makeAsset(['name' => 'Tanah', 'category_code' => AssetCategoryCode::Land->value]);
        $result = $this->depreciation->depreciate($land, '2026-09');
        $this->assertSame(0, $result['amount_idr']);

        $disposable = $this->makeAsset();
        $disposable->update(['status' => AssetStatus::Disposed]);
        $disposed = $this->depreciation->depreciate($disposable, '2026-09');
        $this->assertSame(0, $disposed['amount_idr']);
    }

    public function test_depreciate_command_is_idempotent(): void
    {
        $this->makeAsset();

        $this->artisan('ast:depreciate', ['--period' => '2026-09', '--book' => 'commercial'])->assertSuccessful();
        $first = Depreciation::where('period', '2026-09')->count();
        $this->assertGreaterThan(0, $first);

        $this->artisan('ast:depreciate', ['--period' => '2026-09', '--book' => 'commercial'])->assertSuccessful();
        $this->assertSame($first, Depreciation::where('period', '2026-09')->count());
    }

    // ── 31.3 Revaluasi & impairment (approval) ──────────────────────────

    public function test_revaluation_requires_approval_before_value_changes(): void
    {
        $asset = $this->makeAsset();
        $service = app(RevaluationService::class);

        $requested = $service->request($asset, 'revaluation', 150_000_000, 'Kenaikan nilai wajar', $this->admin);
        $this->assertSame('pending', $requested['status']);
        $this->assertSame('pending', $requested['revaluation']->approval_status);
        $this->assertTrue(ApprovalRequest::whereKey($requested['revaluation']->fresh()->approval_id)->exists(), 'approval_id revaluasi wajib merujuk id core_approvals, bukan uuid.');
        $this->assertSame(120_000_000, $asset->fresh()->book_value_idr); // belum berubah

        $applied = $service->apply($requested['revaluation']);
        $this->assertSame('approved', $applied->approval_status);
        $this->assertSame(150_000_000, $asset->fresh()->book_value_idr);
        $this->assertSame(30_000_000, $applied->difference_idr);
        $this->assertSame(1, $asset->events()->where('event_type', 'revaluation')->count());
    }

    public function test_impairment_lowers_book_value_via_approval(): void
    {
        $asset = $this->makeAsset(['acquisition_cost_idr' => 100_000_000]);
        $service = app(RevaluationService::class);

        $impairment = $service->request($asset, 'impairment', 60_000_000, 'Kerusakan mesin', $this->admin);
        $service->apply($impairment['revaluation']);

        $this->assertSame(60_000_000, $asset->fresh()->book_value_idr);
        $this->assertSame(-40_000_000, $impairment['revaluation']->difference_idr);
    }

    // ── 31.4 Disposal ────────────────────────────────────────────────────

    public function test_disposal_computes_gain_loss_and_marks_asset_disposed(): void
    {
        $asset = $this->makeAsset(['acquisition_cost_idr' => 100_000_000]);
        $service = app(RevaluationService::class);

        $disposal = $service->requestDisposal($asset, DisposalMethod::Sale, 130_000_000, 'Dijual ke pihak ketiga', $this->admin);
        $this->assertSame('pending', $disposal['disposal']->approval_status);
        $this->assertTrue(ApprovalRequest::whereKey($disposal['disposal']->fresh()->approval_id)->exists(), 'approval_id disposal wajib merujuk id core_approvals, bukan uuid.');
        $this->assertSame(30_000_000, $disposal['disposal']->gain_loss_idr);
        $this->assertSame(AssetStatus::InUse, $asset->fresh()->status); // menunggu approval

        $service->finalizeDisposal($disposal['disposal']);

        $this->assertSame(AssetStatus::Disposed, $asset->fresh()->status);
        $this->assertSame('approved', $disposal['disposal']->fresh()->approval_status);
        $this->assertNotNull($disposal['disposal']->fresh()->disposed_at);
        $this->assertSame(1, $asset->events()->where('event_type', 'disposal')->count());

        // Aset disposal tidak disusutkan.
        $dep = app(DepreciationService::class)->depreciate($asset, '2026-09');
        $this->assertSame(0, $dep['amount_idr']);
    }

    public function test_write_off_disposal_has_negative_gain_loss(): void
    {
        $asset = $this->makeAsset(['acquisition_cost_idr' => 50_000_000]);
        $service = app(RevaluationService::class);

        $disposal = $service->requestDisposal($asset, DisposalMethod::WriteOff, 0, 'Hilang karena bencana', $this->admin);
        $this->assertSame(-50_000_000, $disposal['disposal']->gain_loss_idr);
    }

    // ── 31.5 Work order ─────────────────────────────────────────────────

    public function test_work_order_expense_posts_to_expense_account(): void
    {
        $asset = $this->makeAsset(['acquisition_cost_idr' => 100_000_000]);
        $service = app(WorkOrderService::class);

        $wo = $service->schedule($asset, [
            'type' => 'corrective', 'trigger' => 'time', 'due_date' => now()->addWeek()->toDateString(),
            'parts_cost_idr' => 5_000_000, 'labor_cost_idr' => 2_000_000,
            'cost_treatment' => 'expense', 'description' => 'Servis mesin',
        ]);
        $this->assertSame(7_000_000, $wo->total_cost_idr);

        $completed = $service->complete($wo);
        $this->assertSame('completed', $completed->status);

        // Expense tidak menambah nilai aset.
        $this->assertSame(100_000_000, $asset->fresh()->book_value_idr);
        $this->assertSame(7_000_000, (int) LedgerAccount::where('code', 'maintenance:asset:IDR')->first()->money()->amount->toInt());

        // Complete ganda tidak menggandakan biaya (idempoten).
        $service->complete($completed);
        $this->assertSame(7_000_000, (int) LedgerAccount::where('code', 'maintenance:asset:IDR')->first()->money()->amount->toInt());
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_capitalized_work_order_adds_to_asset_cost(): void
    {
        $asset = $this->makeAsset(['acquisition_cost_idr' => 100_000_000]);
        $service = app(WorkOrderService::class);

        $wo = $service->schedule($asset, [
            'type' => 'preventive', 'trigger' => 'usage', 'due_units' => 1000,
            'parts_cost_idr' => 10_000_000, 'cost_treatment' => 'capitalized',
        ]);

        $service->complete($wo, completedUnits: 1000);

        $fresh = $asset->fresh();
        // Landed cost awal 0 + biaya kapitalisasi 10jt = 10jt;
        // book value = 100jt (perolehan) + 10jt (kapitalisasi) = 110jt.
        $this->assertSame(10_000_000, (int) $fresh->landed_cost_idr);
        $this->assertSame(110_000_000, (int) $fresh->book_value_idr);
    }

    public function test_due_work_orders_include_time_and_usage_triggers(): void
    {
        $asset = $this->makeAsset();
        $service = app(WorkOrderService::class);

        $service->schedule($asset, ['trigger' => 'time', 'due_date' => now()->subDay()->toDateString()]);
        $service->schedule($asset, ['trigger' => 'usage', 'due_units' => 500, 'parts_cost_idr' => 1_000_000]);

        AssetUsageLog::create([
            'asset_id' => $asset->id, 'logged_at' => now(), 'units' => 750, 'unit_type' => 'hours', 'source' => 'manual',
        ]);

        $due = $service->dueWorkOrders();
        $this->assertCount(2, $due);
    }

    // ── 31.6 Sewa (PSAK 73 simulasi) ─────────────────────────────────────

    public function test_lease_start_builds_schedule_and_amortizes_interest(): void
    {
        $asset = $this->makeAsset(['acquisition_cost_idr' => 0]);
        $service = app(LeaseService::class);

        $lease = $service->start($asset, [
            'periodic_payment_idr' => 10_000_000,
            'total_periods' => 6,
            'implicit_rate' => 2.0, // 2%/periode (simulasi)
            'start_date' => now()->toDateString(),
        ]);

        $this->assertSame(6, $lease->payments()->count());
        $this->assertGreaterThan(0, $lease->lease_liability_idr);
        $this->assertSame(1, $asset->events()->where('event_type', 'lease')->count());

        $period = $lease->payments()->orderBy('period_no')->first();
        $this->assertSame('pending', $period->status);
        $this->assertGreaterThan(0, $period->interest_portion_idr);

        $service->payPeriod($period, $this->admin->id);

        $freshPeriod = $period->fresh();
        $this->assertSame('paid', $freshPeriod->status);
        $this->assertNotNull($freshPeriod->paid_at);
        $this->assertSame(1, $lease->fresh()->elapsed_periods);

        // Idempoten: bayar dua kali tidak menambah elapsed_periods.
        $service->payPeriod($freshPeriod, $this->admin->id);
        $this->assertSame(1, $lease->fresh()->elapsed_periods);

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    // ── 31.7 TCO ─────────────────────────────────────────────────────────

    public function test_tco_recommends_replace_when_maintenance_exceeds_threshold(): void
    {
        $asset = $this->makeAsset(['acquisition_cost_idr' => 10_000_000]);
        $wo = app(WorkOrderService::class);

        // Pemeliharaan 3.5jt ≥ 30% × 10jt → rekomendasi ganti.
        $woService = $wo;
        foreach ([3_000_000, 500_000] as $cost) {
            $w = $woService->schedule($asset, ['type' => 'corrective', 'parts_cost_idr' => $cost, 'cost_treatment' => 'expense']);
            $woService->complete($w);
        }

        $result = app(AssetTcoService::class)->tco($asset->fresh());

        $this->assertSame(3_500_000, $result['maintenance_idr']);
        $this->assertTrue($result['recommend_replace']);
        $this->assertGreaterThanOrEqual(35.0, $result['tco_percent']);
    }

    public function test_tco_no_replace_for_low_usage_asset(): void
    {
        $asset = $this->makeAsset(['acquisition_cost_idr' => 100_000_000]);

        $result = app(AssetTcoService::class)->tco($asset);
        $this->assertFalse($result['recommend_replace']);
    }

    // ── 31.8 ast:audit + health pillar ──────────────────────────────────

    public function test_asset_audit_balanced_after_depreciation_and_disposal(): void
    {
        $asset = $this->makeAsset();
        $this->depreciation->depreciate($asset, '2026-09', DepreciationMethod::StraightLine);

        $audit = app(AssetAuditService::class)->audit();
        $this->assertTrue($audit['balanced'], json_encode($audit['discrepancies']));
        $this->assertSame('ok', $audit['health_pillars']['ledger']);
        $this->assertSame('ok', $audit['health_pillars']['chain']);

        $this->artisan('ast:audit')->assertSuccessful();

        $health = app(SystemHealthService::class)->check($this->admin);
        $this->assertArrayHasKey('assets', $health['checks']);
        $this->assertTrue($health['checks']['assets']['ok'], $health['checks']['assets']['message']);
        $this->assertSame('HEALTHY', $health['status']);
    }
}
