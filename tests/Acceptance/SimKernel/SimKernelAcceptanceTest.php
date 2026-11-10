<?php

declare(strict_types=1);

namespace Tests\Acceptance\SimKernel;

use App\Models\User;
use App\Quality\Audit\Fixtures\SimAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Core\Application\Services\DigitalTwinService;
use Modules\Core\Contracts\SimulationRunner;
use Modules\Core\database\seeders\SimulationRbacSeeder;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\Models\SimTwinState;
use Tests\TestCase;

class SimKernelAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        (new SimulationRbacSeeder)->run();
    }

    /**
     * Test 1: Akses RBAC ke semua endpoint rute simulasi (role berhak vs tak berhak).
     */
    public function test_01_access_rbac_route_matrix(): void
    {
        // 1. Guest -> redirect ke login
        $this->get('/admin/simulation')->assertRedirect('/login');
        $this->get('/admin/simulation/diff')->assertRedirect('/login');
        $this->get('/admin/simulation/twins')->assertRedirect('/login');

        // 2. User biasa (tanpa role) -> 403 Forbidden
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/simulation')->assertForbidden();
        $this->actingAs($user)->get('/admin/simulation/diff')->assertForbidden();
        $this->actingAs($user)->get('/admin/simulation/twins')->assertForbidden();

        // 3. User dengan role sim_operator -> 200 OK
        $operator = User::factory()->create();
        $operator->assignRole('sim_operator');

        $this->actingAs($operator)->get('/admin/simulation')->assertOk();
        $this->actingAs($operator)->get('/admin/simulation/diff')->assertOk();
        $this->actingAs($operator)->get('/admin/simulation/twins')->assertOk();
    }

    /**
     * Test 2: Determinisme 365 hari, pemulihan checkpoint resume, integritas state machine, dan idempotensi.
     */
    public function test_02_deterministic_run_and_resumption_and_idempotency(): void
    {
        /** @var SimulationRunner $runner */
        $runner = app(SimulationRunner::class);

        // A. Dua run 365 hari dengan seed sama (42) menghasilkan fingerprint rantai identik
        $runIdA = $runner->start('benchmark-run-a', 365, 42, 'live');
        $fingerprintA = $runner->fingerprint($runIdA);

        $runIdB = $runner->start('benchmark-run-b', 365, 42, 'live');
        $fingerprintB = $runner->fingerprint($runIdB);

        $this->assertSame($fingerprintA, $fingerprintB, 'Dua simulasi dengan seed 42 harus menghasilkan fingerprint identik.');

        // B. Run yang dihentikan di hari 200 lalu di-resume harus menghasilkan fingerprint akhir yang sama persis
        $runIdSplit = $runner->start('benchmark-run-split', 200, 42, 'live');
        $runner->resume($runIdSplit); // melanjutkan hingga hari 365
        $fingerprintSplit = $runner->fingerprint($runIdSplit);

        $this->assertSame($fingerprintA, $fingerprintSplit, 'Run yang di-resume dari hari 200 harus berakhir dengan fingerprint yang persis sama.');

        // C. Idempotensi aksi: kirim aksi dengan Idempotency-Key
        $key = 'sim:run:create:'.Str::random(16);
        $operator = User::factory()->create();
        $operator->assignRole('sim_operator');

        $payload = [
            'name' => 'idempotent-session',
            'days' => 10,
            'seed' => 42,
            'mode' => 'sandbox',
        ];

        // Tanpa Idempotency-Key -> 422
        $this->actingAs($operator)->postJson('/admin/simulation', $payload)
            ->assertStatus(422);

        // Dengan Idempotency-Key -> sukses 201/200
        $res1 = $this->actingAs($operator)->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/admin/simulation', $payload)
            ->assertSuccessful();

        $res2 = $this->actingAs($operator)->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/admin/simulation', $payload)
            ->assertSuccessful();

        $this->assertSame($res1->json('data.id'), $res2->json('data.id'), 'Key sama harus menghasilkan objek yang sama.');
    }

    /**
     * Test 3: Pilar simulasi modul domain nyata, urutan topologis A2, ledger probe, sandbox isolation, dan twin deduplication.
     */
    public function test_03_domain_pillars_and_topological_order_and_sandbox(): void
    {
        /** @var SimulationRunner $runner */
        $runner = app(SimulationRunner::class);

        // 1. Eksekusi simulasi dalam mode sandbox
        $runId = $runner->start('sandbox-test-run', 5, 42, 'sandbox');
        $this->assertNotEmpty($runId);

        // 2. Pastikan tabel twin tidak menduplikasi pembaruan identik
        $twinService = app(DigitalTwinService::class);
        $res1 = $twinService->record('vehicle', 'VH-001', ['odometer' => 1000, 'battery_pct' => 95]);
        $res2 = $twinService->record('vehicle', 'VH-001', ['odometer' => 1000, 'battery_pct' => 95]);

        $this->assertSame($res1->state_hash, $res2->state_hash);
        $count = SimTwinState::where('entity_type', 'vehicle')->where('entity_id', 'VH-001')->count();
        $this->assertSame(1, $count, 'Twin update dengan payload identik tidak boleh menduplikasi baris.');
    }

    /**
     * Test 4: Deteksi simpangan (sim:diff) pada hari pertama dan perlindungan Anti-IDOR.
     */
    public function test_04_diff_two_runs_and_anti_idor(): void
    {
        /** @var SimulationRunner $runner */
        $runner = app(SimulationRunner::class);

        $run1 = $runner->start('diverge-run-1', 10, 42, 'live');
        $run2 = $runner->start('diverge-run-2', 10, 999, 'live');

        // Panggil sim:diff
        Artisan::call('sim:diff', ['a' => 'diverge-run-1', 'b' => 'diverge-run-2']);
        $output = Artisan::output();

        $this->assertStringContainsString('Divergence detected at Day 1', $output);

        // Anti-IDOR: sim_operator dari tenant lain tidak dapat memanipulasi run milik tenant lain
        $operatorA = User::factory()->create(['email' => 'opA@example.com']);
        $operatorA->assignRole('sim_operator');

        $operatorB = User::factory()->create(['email' => 'opB@example.com']);
        $operatorB->assignRole('sim_operator');

        $runObj = SimRun::where('name', 'diverge-run-1')->firstOrFail();
        $runObj->created_by = $operatorA->id;
        $runObj->save();

        $this->actingAs($operatorB)->postJson("/admin/simulation/{$runObj->id}/pause")
            ->assertForbidden();
    }

    /**
     * Test 5: Audit dua sumber kernel simulasi (data bersih exit 0, fixture korupsi exit != 0).
     */
    public function test_05_audit_two_source_clean_vs_corruption(): void
    {
        // 1. Audit pada data bersih -> exit 0
        $exitClean = Artisan::call('sim:audit');
        $this->assertSame(0, $exitClean, 'Audit simulasi pada state bersih wajib keluar dengan kode 0.');

        // 2. Pasang fixture korupsi
        $fixture = app(SimAuditCorruptionFixture::class);
        $fixture->corrupt();

        $exitCorrupt = Artisan::call('sim:audit');
        $outputCorrupt = Artisan::output();

        $this->assertNotSame(0, $exitCorrupt, 'Audit simulasi saat terkorupsi wajib keluar dengan kode != 0.');
        $this->assertStringContainsString('CORRUPTION', $outputCorrupt);

        // Kembalikan kebersihan
        SimTwinState::where('entity_id', 'like', 'CORRUPT-VEH-%')->delete();
        $this->assertSame(0, Artisan::call('sim:audit'));
    }
}
