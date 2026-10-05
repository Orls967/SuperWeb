<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Manufacturing\Application\Services\CostingService;
use Modules\Manufacturing\Application\Services\ManufacturingService;
use Modules\Manufacturing\Application\Services\ProductionService;
use Modules\Manufacturing\Application\Services\QualityService;
use Modules\Manufacturing\Domain\Models\Gauge;
use Modules\Manufacturing\Domain\Models\LotSale;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\MaterialBalance;
use Modules\Manufacturing\Domain\Models\MaterialLot;
use Modules\Supplier\Domain\Models\Supplier;
use Tests\TestCase;

/**
 * Regresi Fase 39: rencana inspeksi + AQL, blokir kalibrasi, dispensasi
 * four-eyes, sertifikat memblokir rilis, SPC Cp/Cpk, NCR→CAPA+SCAR,
 * ketertelusuran maju-mundur, recall dengan biaya ledger.
 */
class QualityManagementTest extends TestCase
{
    use RefreshDatabase;

    private QualityService $quality;

    private ManufacturingService $mfg;

    private User $admin;

    private Material $fg;

    private Material $raw;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->quality = app(QualityService::class);
        $this->mfg = app(ManufacturingService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();

        $this->fg = $this->mfg->createMaterial(['code' => 'FG-Q', 'name' => 'Jadi Q', 'kind' => 'finished', 'base_uom' => 'pcs']);
        $this->raw = $this->mfg->createMaterial(['code' => 'RM-Q', 'name' => 'Bahan Q', 'kind' => 'raw', 'base_uom' => 'kg']);
    }

    private function makeLot(string $code = 'RM-Q', float $qty = 10, int $unitCost = 5_000, ?string $sourceRef = null): MaterialLot
    {
        $material = MaterialLot::where('material_id', 'x')->exists()
            ? null
            : Material::where('code', $code)->firstOrFail();

        return MaterialLot::create([
            'material_id' => $material->id,
            'lot_number' => 'Q-'.uniqid(),
            'qty' => $qty, 'unit_cost_idr' => $unitCost,
            'produced_at' => now()->toDateString(), 'source_type' => $sourceRef !== null ? 'production' : 'purchase',
            'source_ref' => $sourceRef, 'status' => 'active',
        ]);
    }

    // ── 39.1/39.2 Rencana + inspeksi ─────────────────────────────────────

    public function test_inspection_plan_and_pass_fail_with_aql(): void
    {
        $plan = $this->quality->createInspectionPlan([
            'name' => 'Berat kemasan', 'stage' => 'in_process',
            'spec_min' => 95, 'spec_max' => 105, 'aql_percent' => 5, 'sample_size' => 5,
        ], [['name' => 'berat', 'type' => 'variable']]);

        $pass = $this->quality->inspect('in_process', 'production_order', (string) Str::uuid(), [100, 99, 101, 100, 98], $this->admin, $plan->id);
        $this->assertSame('passed', $pass['result']);
        $this->assertSame(0, $pass['out_of_spec']);

        // 60% di luar spesifikasi > AQL 5% → gagal.
        $fail = $this->quality->inspect('in_process', 'production_order', (string) Str::uuid(), [100, 200, 200, 200, 100], $this->admin, $plan->id);
        $this->assertSame('failed', $fail['result']);
        $this->assertSame(3, $fail['out_of_spec']);
    }

    public function test_inspection_without_readings_rejected(): void
    {
        try {
            $this->quality->inspect('final', 'lot', 'x', [], $this->admin);
            $this->fail('Inspeksi tanpa pembacaan harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('pembacaan', $e->getMessage());
        }
    }

    // ── 39.8 Kalibrasi memblokir inspeksi ────────────────────────────────

    public function test_expired_gauge_blocks_inspection(): void
    {
        $expired = Gauge::create([
            'code' => 'CAL-01', 'name' => 'Vernier kadaluarsa',
            'calibrated_at' => now()->subYear(), 'calibration_due' => now()->subDay(),
        ]);
        $valid = Gauge::create([
            'code' => 'CAL-02', 'name' => 'Vernier valid',
            'calibrated_at' => now(), 'calibration_due' => now()->addYear(),
        ]);

        $this->assertTrue($valid->isCalibrationValid());
        $this->assertFalse($expired->isCalibrationValid());

        try {
            $this->quality->inspect('final', 'production_order', 'x', [100], $this->admin, null, $expired->id);
            $this->fail('Alat ukur kadaluarsa harus memblokir inspeksi.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('kalibrasi', mb_strtolower($e->getMessage()));
        }

        $ok = $this->quality->inspect('final', 'production_order', 'x', [100], $this->admin, null, $valid->id);
        $this->assertSame('passed', $ok['result']);
    }

    // ── 39.2 Dispensasi four-eyes ────────────────────────────────────────

    public function test_waive_inspection_requires_four_eyes(): void
    {
        $plan = $this->quality->createInspectionPlan([
            'name' => 'Uji dispensasi', 'stage' => 'final',
            'spec_min' => 90, 'spec_max' => 110, 'aql_percent' => 0, 'sample_size' => 2,
        ], [['name' => 'nilai', 'type' => 'variable']]);

        $fail = $this->quality->inspect('final', 'production_order', (string) Str::uuid(), [10, 200], $this->admin, $plan->id);
        $inspection = $fail['inspection'];
        $this->assertSame('failed', $inspection->result);

        // Ajukan dispensasi sebagai admin; approve oleh diri sendiri harus
        // ditolak prinsip empat mata.
        $submitted = $this->quality->submitWaiver($inspection, $this->admin, 'Cacat kosmetik');
        $this->assertSame('failed', $submitted->result, 'Hasil tetap gagal sampai disetujui.');
        $this->assertNotNull($submitted->approval_id);

        try {
            $this->quality->approveWaiver($submitted, $this->admin, 'self');
            $this->fail('Four-eyes dilanggar.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Four-Eyes', $e->getMessage());
        }

        $reviewer = User::factory()->create(['role' => 'admin']);
        $waived = $this->quality->approveWaiver($submitted->fresh(), $reviewer, 'Cacat kosmetik diterima');
        $this->assertSame('waived', $waived->result);
        $this->assertStringContainsString('Dispensasi', (string) $waived->findings);
    }

    // ── 39.7 Sertifikat memblokir rilis ──────────────────────────────────

    public function test_certificate_expiry_blocks_lot_release(): void
    {
        $lot = $this->makeLot();
        $lot->update(['status' => 'blocked']);

        // Tanpa sertifikat → diblokir.
        try {
            $this->quality->releaseLot($lot->fresh(), $this->admin);
            $this->fail('Rilis tanpa sertifikat harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('coa', $e->getMessage());
        }

        // Sertifikat kedaluwarsa → tetap diblokir.
        $this->quality->addCertificate($lot, 'coa', 'COA-1', now()->subDay()->toDateString(), $this->admin);
        try {
            $this->quality->releaseLot($lot->fresh(), $this->admin);
            $this->fail('Sertifikat kedaluwarsa harus memblokir rilis.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        // Sertifikat berlaku → rilis.
        $this->quality->addCertificate($lot, 'coa', 'COA-2', now()->addYear()->toDateString(), $this->admin);
        $released = $this->quality->releaseLot($lot->fresh(), $this->admin);
        $this->assertSame('active', $released->status);
    }

    // ── 39.3 SPC ─────────────────────────────────────────────────────────

    public function test_spc_computes_cp_cpk_and_flags_out_of_control(): void
    {
        $plan = $this->quality->createInspectionPlan([
            'name' => 'Diameter', 'stage' => 'in_process',
            'spec_min' => 10, 'spec_max' => 12, 'sample_size' => 5,
        ], [['name' => 'diameter', 'type' => 'variable']]);

        // Subgroup terkendali.
        $r1 = $this->quality->recordSpcSample($plan, [11, 11.2, 10.9, 11.1, 10.8]);
        $this->assertArrayHasKey('cp', $r1);
        $this->assertSame(0.0, $r1['cp'], 'Subgroup pertama: Cp/Cpk butuh ≥ 2 riwayat.');

        $r2 = $this->quality->recordSpcSample($plan, [11.1, 11, 10.9, 11.2, 11]);
        $this->assertTrue($r2['in_control'], "Cpk {$r2['cpk']} harus ≥ 1 untuk data terkendali.");

        // Mean jauh di luar spesifikasi → alarm.
        $r3 = $this->quality->recordSpcSample($plan, [13, 13.1, 13, 13.2, 13]);
        $this->assertFalse($r3['in_control'], 'Mean di luar USL harus memicu alarm.');

        // Cp/Cpk dari riwayat ≥ 2 sample.
        [$cp, $cpk] = $this->quality->cpCpk($plan, $plan->spcSamples()->orderBy('subgroup')->get());
        $this->assertGreaterThan(0, $cp);
        $this->assertIsFloat($cpk);
    }

    // ── 39.4 NCR + SCAR + CAPA ───────────────────────────────────────────

    public function test_ncr_links_scar_and_capa_lifecycle_closes_ncr(): void
    {
        $supplier = Supplier::where('is_active', true)->firstOrFail();

        $ncr = $this->quality->openNcr([
            'source' => 'supplier', 'supplier_id' => $supplier->id,
            'severity' => 'major', 'title' => 'Bahan tidak sesuai spesifikasi',
            'due_date' => now()->addDays(7)->toDateString(),
        ], $this->admin);

        $this->assertStringStartsWith('NCR/', $ncr->number);
        $this->assertNotNull($ncr->scar_ref, 'NCR pemasok otomatis membentuk SCAR.');
        $this->assertSame('investigating', $ncr->fresh()->status);
        $this->assertSame(1, DB::table('sup_risk_flags')->where('supplier_id', $supplier->id)->where('type', 'scar')->count());

        $capa = $this->quality->addCapa($ncr, 'corrective', 'Kalibrasi proses mixing', now()->addDays(3)->toDateString());
        $this->assertSame('capa', $ncr->fresh()->status);
        $this->assertTrue($capa->isOverdue() === false);

        $done = $this->quality->completeCapa($capa, 'effective', 'Verifikasi 3 batch lolos');
        $this->assertSame('verified', $done->status);
        $this->assertSame('closed', $ncr->fresh()->status);
        $this->assertNotNull($ncr->fresh()->closed_at);
    }

    public function test_overdue_capa_sweep(): void
    {
        $ncr = $this->quality->openNcr(['title' => 'Tertinggal', 'due_date' => now()->addDays(1)->toDateString()], $this->admin);
        $this->quality->addCapa($ncr, 'preventive', 'Update SOP', now()->subDay()->toDateString());

        $count = $this->quality->markOverdueCapas();
        $this->assertSame(1, $count);
        $this->assertSame('overdue', $ncr->capas()->first()->status);
    }

    // ── 39.5 Ketertelusuran ──────────────────────────────────────────────

    public function test_trace_forward_records_sales_and_lists_recipients(): void
    {
        $fgLot = $this->makeLot('FG-Q', 5, 20_000, 'MPO/Q/00001');
        $sale = LotSale::create([
            'lot_id' => $fgLot->id, 'store_order_item_id' => 77, 'store_order_id' => 88,
            'user_id' => $this->admin->id, 'qty' => 2, 'cost_idr' => 40_000,
        ]);

        $forward = $this->quality->traceForward($fgLot->id);
        $this->assertCount(1, $forward);
        $this->assertSame(88, $forward[0]['order']);
        $this->assertSame(2.0, (float) $forward[0]['qty']);
        unset($sale);
    }

    public function test_trace_backward_lists_input_lots(): void
    {
        $inputLot = $this->makeLot('RM-Q', 10, 5_000);
        MaterialBalance::create(['material_id' => $this->raw->id, 'qty_on_hand' => 10, 'qty_reserved' => 0]);

        // Buat order + issue ber-alokasi lot, lalu lot FG yang merujuk order itu.
        $production = app(ProductionService::class);
        $order = $production->createProductionOrder([
            'material_id' => $this->fg->id, 'qty' => 2, 'due_date' => now()->addDays(2)->toDateString(),
        ], $this->admin);
        $production->transition($order, 'released', $this->admin);
        $production->issueMaterials($order->fresh(), [
            ['material_id' => $this->raw->id, 'qty' => 4, 'method' => 'fifo'],
        ], $this->admin);

        $fgLot = $this->makeLot('FG-Q', 2, 0, $order->number);

        $backward = $this->quality->traceBackward($fgLot->id);
        $this->assertNotEmpty($backward['inputs'], 'Jejak mundur menemukan lot bahan.');
        $this->assertSame($inputLot->lot_number, $backward['inputs'][0]['lot']);
        $this->assertSame('RM-Q', $backward['inputs'][0]['material']);
    }

    // ── 39.6 Recall ──────────────────────────────────────────────────────

    public function test_recall_is_idempotent_blocks_lot_and_posts_cost(): void
    {
        $lot = $this->makeLot('FG-Q', 3, 20_000, 'MPO/Q/RECALL');
        LotSale::create([
            'lot_id' => $lot->id, 'store_order_item_id' => 5, 'store_order_id' => 9,
            'user_id' => $this->admin->id, 'qty' => 1, 'cost_idr' => 20_000,
        ]);

        $recall = $this->quality->planRecall($lot, 'Kontaminasi terdeteksi', $this->admin);
        $this->assertSame('planned', $recall->status);
        $this->assertSame('blocked', $lot->fresh()->status);
        $this->assertCount(1, $recall->recipients);

        // Idempoten: recall kedua untuk lot sama tidak membuat ganda.
        $again = $this->quality->planRecall($lot->fresh(), 'Alasan lain', $this->admin);
        $this->assertSame($recall->id, $again->id);

        $notified = $this->quality->notifyRecall($recall);
        $this->assertSame(1, $notified);
        $this->assertSame('notified', $recall->fresh()->status);

        $result = $this->quality->completeRecall($recall->fresh(), 150_000, 'Dimusnahkan di incinerator sertifikat UJI-1');
        $this->assertSame('completed', $result['recall']->status);
        $this->assertSame(150_000, (int) $result['recall']->cost_idr);
        $this->assertSame('consumed', $lot->fresh()->status);
        $this->assertSame(0.0, (float) $lot->fresh()->qty);

        // Biaya recall → beban scrap, WIP netral.
        $this->assertSame(
            150_000,
            (int) LedgerAccount::where('code', CostingService::ACCT_SCRAP)->value('cached_balance')
        );
        $this->artisan('bank:reconcile')->assertSuccessful();

        // Replay aman.
        $replay = $this->quality->completeRecall($result['recall'], 150_000, 'sama');
        $this->assertSame(150_000, (int) $replay['recall']->cost_idr);
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    // ── Route render ─────────────────────────────────────────────────────

    public function test_quality_pages_render(): void
    {
        $lot = $this->makeLot();

        $this->actingAs($this->admin)
            ->get(route('manufacturing.quality.index'))
            ->assertOk()
            // Teks statis dalam HTML tidak di-escape Blade → escape: false.
            ->assertSee('Mutu & Ketertelusuran', false);

        $this->actingAs($this->admin)
            ->get(route('manufacturing.quality.lots.trace', $lot))
            ->assertOk()
            ->assertSee('Ketertelusuran');
    }
}
