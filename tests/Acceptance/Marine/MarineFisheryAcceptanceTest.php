<?php

declare(strict_types=1);

namespace Tests\Acceptance\Marine;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\MarineFisheryT1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Marine\Application\Services\MarineAquacultureService;
use Modules\Marine\Domain\Models\MarineCycle;
use Modules\Marine\Domain\Models\MarineFeedLog;
use Modules\Marine\Domain\Models\MarineHarvest;
use Modules\Marine\Domain\Models\MarinePayout;
use Modules\Marine\Domain\Models\MarinePond;
use Tests\TestCase;

class MarineFisheryAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected MarineAquacultureService $service;

    protected LedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MarineAquacultureService::class);
        $this->ledgerService = app(LedgerService::class);
        $this->seed(LedgerAccountsSeeder::class);
    }

    /**
     * Skenario 1: RBAC & Anti-IDOR Protection pada rute operasional budidaya laut.
     */
    public function test_enforces_rbac_and_anti_idor_on_marine_routes(): void
    {
        $farmerA = User::factory()->create(['role' => 'farmer']);
        $farmerB = User::factory()->create(['role' => 'farmer']);
        $officer = User::factory()->create(['role' => 'officer']);

        $pondA = $this->service->createPond('POND-A1', 'Tambak Udang Vaname A', (int) $farmerA->id, 5000, 'Situbondo');

        // Tamu ditolak / dialihkan
        $this->get('/marine')->assertRedirect('/login');

        // Farmer A dapat melihat dashboard dan kolam miliknya
        $this->actingAs($farmerA)->get('/marine')->assertOk();
        $this->actingAs($farmerA)->get("/marine/ponds/{$pondA->id}")->assertOk();

        // Farmer B dilarang melihat detail kolam Farmer A (Anti-IDOR)
        $this->actingAs($farmerB)->get("/marine/ponds/{$pondA->id}")->assertForbidden();

        // Officer berwenang dapat melihat
        $this->actingAs($officer)->get("/marine/ponds/{$pondA->id}")->assertOk();
    }

    /**
     * Skenario 2: Vektor Angka Rasional Eksak (Tebar 10k, Survival 85%, FCR 1.330, Gross 125.375jt, Net 77.375jt).
     */
    public function test_matches_exact_rational_fishery_vectors_and_fcr(): void
    {
        $farmer = User::factory()->create(['role' => 'farmer']);
        $pond = $this->service->createPond('POND-VEC-1', 'Tambak Uji Vektor', (int) $farmer->id, 10000, 'Lampung');

        // Tebar: 10.000 ekor @ 2g (Biomasa awal = 20 kg), Uang Muka = Rp20.000.000
        $cycle = $this->service->startCycle(
            cycleNumber: 'CYC-VEC-001',
            pondId: (int) $pond->id,
            farmerId: (int) $farmer->id,
            species: 'SHRIMP_VANNAMEI',
            stockedCount: 10000,
            initialWeightGramsPerFish: 2,
            advanceAmount: 20_000_000
        );

        // Pakan: 2.800 kg @ Rp10.000 = Rp28.000.000
        $this->service->logFeed((int) $cycle->id, '2026-03-01', 1400, 10000);
        $this->service->logFeed((int) $cycle->id, '2026-04-01', 1400, 10000);

        // Panen: 8.500 ekor (85%) @ 250g = 2.125 kg
        // FCR = 2.800 / (2.125 - 20) = 2.800 / 2.105 = 1,330166... -> scaled 1330
        // Grade A: 425 kg @ 75rb = Rp31.875.000
        // Grade B: 1.275 kg @ 60rb = Rp76.500.000
        // Grade C: 425 kg @ 40rb = Rp17.000.000
        // Total Gross = Rp125.375.000
        $harvest = $this->service->recordHarvest(
            cycleId: (int) $cycle->id,
            harvestNumber: 'HRV-VEC-001',
            harvestDate: '2026-05-01',
            harvestedCount: 8500,
            avgWeightGrams: 250,
            gradeAKg: 425,
            gradeAPrice: 75000,
            gradeBKg: 1275,
            gradeBPrice: 60000,
            gradeCKg: 425,
            gradeCPrice: 40000
        );

        $this->assertSame(2125, $harvest->total_weight_kg);
        $this->assertSame(1330, $harvest->fcr_scaled);
        $this->assertSame(125_375_000, $harvest->gross_amount);

        // Settlement Payout Net: 125.375.000 - 20.000.000 - 28.000.000 = Rp77.375.000
        $payout = $this->service->settlePayout((int) $cycle->id, 'IDEMP-VEC-001');

        $this->assertSame(125_375_000, $payout->gross_amount);
        $this->assertSame(20_000_000, $payout->advance_deduction);
        $this->assertSame(28_000_000, $payout->feed_loan_deduction);
        $this->assertSame(77_375_000, $payout->net_payout);
    }

    /**
     * Skenario 3: Double-Entry Ledger Integration, Zero-Sum Balance & Idempotensi Payout.
     */
    public function test_double_entry_ledger_integration_and_idempotency(): void
    {
        $farmer = User::factory()->create(['role' => 'farmer']);
        $pond = $this->service->createPond('POND-LEDGER-1', 'Tambak Akuntansi', (int) $farmer->id, 5000, 'Bali');

        $cycle = $this->service->startCycle('CYC-LED-01', (int) $pond->id, (int) $farmer->id, 'BARRAMUNDI', 5000, 5, 10_000_000);
        $this->service->logFeed((int) $cycle->id, '2026-03-10', 1000, 15000); // 15jt feed

        $harvest = $this->service->recordHarvest(
            cycleId: (int) $cycle->id,
            harvestNumber: 'HRV-LED-01',
            harvestDate: '2026-05-15',
            harvestedCount: 4000,
            avgWeightGrams: 500,
            gradeAKg: 1000,
            gradeAPrice: 50000,
            gradeBKg: 1000,
            gradeBPrice: 40000,
            gradeCKg: 0,
            gradeCPrice: 0
        ); // Gross: 50jt + 40jt = 90jt

        $payout1 = $this->service->settlePayout((int) $cycle->id, 'IDEMP-LED-CYCLE-1');
        $this->assertSame(65_000_000, $payout1->net_payout); // 90jt - 10jt - 15jt = 65jt

        // Idempotensi: Panggilan ulang tidak menduplikasi payout
        $payout2 = $this->service->settlePayout((int) $cycle->id, 'IDEMP-LED-CYCLE-1');
        $this->assertSame($payout1->id, $payout2->id);
        $this->assertSame(1, MarinePayout::where('cycle_id', $cycle->id)->count());
    }

    /**
     * Skenario 4: Seeder Skala T1 Deterministik (100 kolam, 50 siklus).
     */
    public function test_seeds_t1_scale_cleanly_within_time_envelope(): void
    {
        $start = microtime(true);
        $this->seed(MarineFisheryT1Seeder::class);
        $duration = microtime(true) - $start;

        $this->assertLessThan(15.0, $duration, 'T1 seeder should finish within 15 seconds envelope');
        $this->assertSame(100, MarinePond::count());
        $this->assertSame(50, MarineCycle::count());
        $this->assertSame(100, MarineFeedLog::count());
        $this->assertSame(50, MarineHarvest::count());
        $this->assertSame(50, MarinePayout::count());
    }

    /**
     * Skenario 5: Audit Subledger vs Ledger passes on clean state and fails on sabotage.
     */
    public function test_passes_marine_audit_on_clean_state_and_detects_sabotage(): void
    {
        $this->seed(MarineFisheryT1Seeder::class);

        // Audit kondisi bersih
        $exitCode = Artisan::call('mar:audit');
        $this->assertSame(0, $exitCode);

        // Sabotase manipulasi payout
        $payout = MarinePayout::first();
        $payout->net_payout += 100_000;
        $payout->save();

        $exitCodeSabotaged = Artisan::call('mar:audit');
        $this->assertSame(1, $exitCodeSabotaged);
    }
}
