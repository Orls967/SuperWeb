<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Services\Scale\OutboxScaleProvisioner;
use Modules\Core\Application\Services\Scale\PassportScaleProvisioner;
use Tests\TestCase;

class ScaleProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_modify_database(): void
    {
        $exit = Artisan::call('scale:provision', [
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame(0, DB::table('sim_scale_checkpoints')->count());
    }

    public function test_ledger_scale_provisioner_creates_balanced_entries_with_checkpoints(): void
    {
        $exit = Artisan::call('scale:provision', [
            '--module' => 'ledger',
            '--tier' => 'T1',
            '--count' => 60,
            '--chunk' => 25,
            '--seed' => 99,
        ]);

        $this->assertSame(0, $exit);

        $checkpoint = DB::table('sim_scale_checkpoints')
            ->where('provisioner_key', 'ledger')
            ->where('tier', 'T1')
            ->where('seed', 99)
            ->first();

        $this->assertNotNull($checkpoint);
        $this->assertSame('completed', $checkpoint->status);
        $this->assertSame(60, (int) $checkpoint->total_inserted);
        $this->assertSame(60, (int) $checkpoint->last_processed_step);

        // Verify balance across entries
        $sum = DB::table('bank_ledger_entries')->sum('amount');
        $this->assertEquals(0.0, (float) $sum);

        // Resume call should be idempotent
        $resumedExit = Artisan::call('scale:provision', [
            '--module' => 'ledger',
            '--tier' => 'T1',
            '--count' => 60,
            '--chunk' => 25,
            '--seed' => 99,
            '--resume' => true,
        ]);
        $this->assertSame(0, $resumedExit);
    }

    public function test_passport_and_outbox_provisioners_produce_valid_records(): void
    {
        /** @var PassportScaleProvisioner $passportProvisioner */
        $passportProvisioner = app(PassportScaleProvisioner::class);
        $pInserted = $passportProvisioner->provision(
            tier: 'T1',
            targetCount: 40,
            chunkSize: 15,
            seed: 88
        );
        $this->assertSame(40, $pInserted);

        /** @var OutboxScaleProvisioner $outboxProvisioner */
        $outboxProvisioner = app(OutboxScaleProvisioner::class);
        $oInserted = $outboxProvisioner->provision(
            tier: 'T1',
            targetCount: 50,
            chunkSize: 20,
            seed: 77
        );
        $this->assertSame(50, $oInserted);

        $this->assertSame(40, DB::table('core_vehicle_events')->count());
        $this->assertSame(50, DB::table('core_outbox')->count());
    }

    public function test_artisan_scale_provision_command_runs_for_all_modules(): void
    {
        $exit = Artisan::call('scale:provision', [
            '--module' => 'all',
            '--tier' => 'T1',
            '--count' => 30,
            '--chunk' => 15,
            '--seed' => 42,
        ]);

        $this->assertSame(0, $exit);

        $checkpoints = DB::table('sim_scale_checkpoints')->get();
        $this->assertCount(3, $checkpoints);

        // Passport verification command must pass
        $exitVerify = Artisan::call('core:verify-passports');
        $this->assertSame(0, $exitVerify);

        // Reconcile must pass with 0 discrepancy
        $exitReconcile = Artisan::call('bank:reconcile');
        $this->assertSame(0, $exitReconcile);
    }
}
