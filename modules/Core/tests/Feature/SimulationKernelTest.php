<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Contracts\DigitalTwinInterface;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Core\Domain\Models\SimSeederCheckpoint;
use Tests\TestCase;

class SimulationKernelTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulation_clock_advancement_is_deterministic(): void
    {
        /** @var SimClockInterface $clock */
        $clock = app(SimClockInterface::class);

        // Run 1
        $start = Carbon::parse('2026-01-01 00:00:00');
        $run1 = $clock->initRun('run-alpha', $start, 12345);
        $time1 = $clock->advanceDays(365);
        $clock->reset();

        // Run 2
        $run2 = $clock->initRun('run-beta', $start, 12345);
        $time2 = $clock->advanceDays(365);
        $clock->reset();

        $this->assertEquals($time1->toIso8601String(), $time2->toIso8601String());
        $this->assertEquals('2027-01-01T00:00:00+00:00', $time1->toIso8601String());
    }

    public function test_event_spine_publish_idempotency_and_replay_from_offset(): void
    {
        /** @var EventSpineInterface $spine */
        $spine = app(EventSpineInterface::class);

        $payload = ['amount' => 500000, 'reference' => 'TX-001'];
        $event1 = $spine->publish('fintech.wallet', 'WalletCredited', $payload, 'IDEMP-SPINE-001');
        $event2 = $spine->publish('fintech.wallet', 'WalletCredited', $payload, 'IDEMP-SPINE-001');

        $this->assertEquals($event1->id, $event2->id);

        $spine->publish('fintech.wallet', 'WalletDebited', ['amount' => 200000], 'IDEMP-SPINE-002');
        $spine->publish('auto.service', 'ServiceCompleted', ['service' => 'Oil Change'], 'IDEMP-SPINE-003');

        // Consume all fintech.*
        $fintechEvents = $spine->consume('fintech.*', 0);
        $this->assertCount(2, $fintechEvents);

        // Commit offset and consume from offset
        $spine->commitOffset('group-accounting', 'fintech.*', $event1->id);
        $offset = $spine->getOffset('group-accounting', 'fintech.*');
        $this->assertEquals($event1->id, $offset);

        $remaining = $spine->consume('fintech.*', $offset);
        $this->assertCount(1, $remaining);
        $this->assertEquals('WalletDebited', $remaining->first()->event_name);
    }

    public function test_digital_twin_hash_chain_and_idempotency(): void
    {
        /** @var DigitalTwinInterface $twin */
        $twin = app(DigitalTwinInterface::class);

        $state1 = ['mileage' => 10000, 'battery_soh' => 98];
        $twin1 = $twin->recordState('vehicle', 'VEH-001', $state1);

        // Duplicate update with same state should be idempotent
        $twinDuplicate = $twin->recordState('vehicle', 'VEH-001', $state1);
        $this->assertEquals($twin1->id, $twinDuplicate->id);

        // Advance state
        $state2 = ['mileage' => 15000, 'battery_soh' => 95];
        $twin2 = $twin->recordState('vehicle', 'VEH-001', $state2);

        $this->assertNotEquals($twin1->id, $twin2->id);
        $this->assertEquals($twin1->state_hash, $twin2->prev_hash);

        // Verify chain integrity
        $isValid = $twin->verifyChain('vehicle', 'VEH-001');
        $this->assertTrue($isValid);
    }

    public function test_seeder_checkpoint_resume_without_duplicate(): void
    {
        $checkpoint = SimSeederCheckpoint::updateOrCreate(
            ['seeder_name' => 'ScaleVehicleSeeder', 'stage' => 'chunk_1'],
            ['last_processed_id' => 100, 'batch_number' => 1, 'completed' => true]
        );

        $this->assertEquals(100, $checkpoint->last_processed_id);
        $this->assertTrue($checkpoint->completed);

        // Resuming: checkpoint exists, avoids duplicate re-run
        $existing = SimSeederCheckpoint::where('seeder_name', 'ScaleVehicleSeeder')
            ->where('stage', 'chunk_1')
            ->first();

        $this->assertNotNull($existing);
        $this->assertTrue($existing->completed);
    }

    public function test_artisan_sim_run_command_execution(): void
    {
        $exitCode = Artisan::call('sim:run', [
            '--days' => 5,
            '--seed' => 999,
            '--name' => 'test-artisan-run',
        ]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Simulation Run [test-artisan-run]', $output);
        $this->assertStringContainsString('Advanced 5 days', $output);
    }
}
