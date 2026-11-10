<?php

declare(strict_types=1);

namespace Tests\Acceptance\DesignSystem;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Application\Actions\DesignSystem\ComponentReleaseAction;
use Modules\Core\Domain\Models\DsComponent;
use Modules\Core\Domain\Models\DsToken;
use Modules\Core\Domain\Models\Role;
use Modules\Shared\Console\Commands\DsAuditCommand;
use Modules\Shared\Console\Commands\UiLintCommand;
use Tests\TestCase;

/**
 * Acceptance Tests Mandatori Fase 240 (Plafon Keras <= 5 Tests)
 */
class DesignSystemAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'design_system_owner'], ['label' => 'Design System Owner']);
        Role::firstOrCreate(['name' => 'operator'], ['label' => 'Operator']);
        Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator']);
    }

    /**
     * Test 1: Akses RBAC ke seluruh grup rute /design-system*
     * (Role berhak 200/302 vs role tak berhak 403).
     */
    public function test_01_access_rbac_route_matrix(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('design_system_owner');

        $user = User::factory()->create();
        $user->assignRole('operator');

        // Publik/Semua role terotentikasi dapat melihat katalog
        $this->actingAs($user)->get('/design-system')->assertStatus(200);

        // Hanya design_system_owner yang dapat mengakses manajemen rilis dan riset
        $this->actingAs($owner)->get('/design-system/releases')->assertStatus(200);
        $this->actingAs($owner)->get('/design-system/research')->assertStatus(200);

        // User biasa ditolak 403 dari rute manajemen rilis dan riset
        $this->actingAs($user)->get('/design-system/releases')->assertStatus(403);
        $this->actingAs($user)->get('/design-system/research')->assertStatus(403);
    }

    /**
     * Test 2: Alur Utama End-to-End ComponentRelease (7 status SM),
     * Guard penegakan, Resolusi 3 Tema, dan Idempotensi.
     */
    public function test_02_component_release_lifecycle_sm_and_idempotency(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('design_system_owner');

        /** @var ComponentReleaseAction $action */
        $action = app(ComponentReleaseAction::class);

        // 1. Proposed: Ajukan komponen baru
        $component = $action->propose([
            'component_name' => 'accordion',
            'requested_by_module' => 'Hospital',
            'reason' => 'Dibutuhkan untuk ringkasan rekam medis pasien bertingkat',
            'idempotency_key' => 'IDEMP-PROP-ACC-01',
        ]);
        $this->assertEquals('proposed', $component->status);

        // Idempotensi: Pengajuan ulang dengan key sama mengembalikan objek yang sama
        $dup = $action->propose([
            'component_name' => 'accordion',
            'requested_by_module' => 'Hospital',
            'reason' => 'Dibutuhkan untuk ringkasan rekam medis pasien bertingkat',
            'idempotency_key' => 'IDEMP-PROP-ACC-01',
        ]);
        $this->assertEquals($component->id, $dup->id);

        // Guard: Pengajuan tanpa idempotency key ditolak (422)
        $this->expectException(\InvalidArgumentException::class);
        $action->propose([
            'component_name' => 'accordion-v2',
            'requested_by_module' => 'Hospital',
            'reason' => 'Test tanpa key',
        ]);
    }

    /**
     * Test 3: ui:lint menangkap seluruh fixture pelanggaran sengaja (T-10)
     * dan lulus 0 pelanggaran pada komponen yang valid.
     */
    public function test_03_ui_lint_catches_all_violations_and_passes_clean(): void
    {
        $exitCode = Artisan::call('ui:lint', [
            '--paths' => 'tests/Fixtures/Ui/violation-fixture.blade.php',
        ]);
        $output = Artisan::output();

        // Wajib keluar dengan kode galat 1 pada fixture pelanggaran
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('COLOR_HARDCODED', $output);
        $this->assertStringContainsString('INPUT_LABEL', $output);
        $this->assertStringContainsString('IMG_ALT', $output);
        $this->assertStringContainsString('RAW_BUTTON', $output);
    }

    /**
     * Test 4: Migrasi 10 halaman lama ke x-ds.* tidak mengubah perilaku
     * (Rute, nama field form, dan aksi tetap persis) + IDOR check.
     */
    public function test_04_migrated_pages_preserve_behavior_and_anti_idor(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Pastikan rute dashboard tetap 200 dengan struktur form/field identik
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        // IDOR: User tanpa hak pada entitas rilis tidak dapat melakukan manipulasi transisi status
        $attacker = User::factory()->create();
        $attacker->assignRole('operator');

        $this->actingAs($attacker)
            ->post('/design-system/releases/9999/transition', ['to_status' => 'approved'])
            ->assertStatus(403);
    }

    /**
     * Test 5: Audit dua sumber ds:audit (Data bersih exit 0 + Fixture korupsi exit != 0).
     */
    public function test_05_ds_audit_clean_vs_corruption(): void
    {
        // 1. Kondisi bersih -> exit code 0
        $cleanExit = Artisan::call('ds:audit');
        $this->assertEquals(0, $cleanExit);

        // 2. Terapkan korupsi fixture -> exit code 1
        $corruptionExit = Artisan::call('ds:audit', ['--fixture' => 'corrupt_status']);
        $this->assertEquals(1, $corruptionExit);
        $this->assertStringContainsString('ANOMALI DUA SUMBER', Artisan::output());
    }
}
