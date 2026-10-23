<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Domain\Models\SimRun;
use Tests\TestCase;

class SimulationDeterministicRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_identical_simulation_runs_produce_identical_state_hash(): void
    {
        $exit1 = Artisan::call('sim:run', [
            '--days' => 5,
            '--seed' => 98765,
            '--name' => 'run-alpha',
        ]);
        $this->assertEquals(0, $exit1);
        $runAlpha = SimRun::where('name', 'run-alpha')->first();
        $this->assertNotNull($runAlpha);
        $this->assertEquals(5, $runAlpha->last_completed_day);
        $this->assertNotEmpty($runAlpha->last_state_hash);

        $exit2 = Artisan::call('sim:run', [
            '--days' => 5,
            '--seed' => 98765,
            '--name' => 'run-beta',
        ]);
        $this->assertEquals(0, $exit2);
        $runBeta = SimRun::where('name', 'run-beta')->first();
        $this->assertNotNull($runBeta);
        $this->assertEquals(5, $runBeta->last_completed_day);

        // Deterministic state equality
        $this->assertEquals($runAlpha->last_state_hash, $runBeta->last_state_hash);
    }

    public function test_different_seeds_produce_different_state_hashes(): void
    {
        Artisan::call('sim:run', [
            '--days' => 3,
            '--seed' => 11111,
            '--name' => 'run-seed-1',
        ]);
        $run1 = SimRun::where('name', 'run-seed-1')->first();

        Artisan::call('sim:run', [
            '--days' => 3,
            '--seed' => 22222,
            '--name' => 'run-seed-2',
        ]);
        $run2 = SimRun::where('name', 'run-seed-2')->first();

        $this->assertNotEquals($run1->last_state_hash, $run2->last_state_hash);
    }

    public function test_simulation_resume_matches_continuous_run(): void
    {
        // 1. Run 4 days continuously
        Artisan::call('sim:run', [
            '--days' => 4,
            '--seed' => 54321,
            '--name' => 'continuous-run',
        ]);
        $continuous = SimRun::where('name', 'continuous-run')->first();
        $this->assertEquals(4, $continuous->last_completed_day);

        // 2. Run 2 days, then resume 2 more days
        Artisan::call('sim:run', [
            '--days' => 2,
            '--seed' => 54321,
            '--name' => 'chunked-run',
        ]);
        $chunk1 = SimRun::where('name', 'chunked-run')->first();
        $this->assertEquals(2, $chunk1->last_completed_day);

        // Resume for 2 more days
        Artisan::call('sim:run', [
            '--days' => 2,
            '--name' => 'chunked-run',
            '--resume' => true,
        ]);
        $chunk2 = SimRun::where('name', 'chunked-run')->first();
        $this->assertEquals(4, $chunk2->last_completed_day);

        // Hash of 4-day continuous run should match resumed 4-day run
        $this->assertEquals($continuous->last_state_hash, $chunk2->last_state_hash);
    }
}
