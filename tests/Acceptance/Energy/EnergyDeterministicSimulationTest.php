<?php

declare(strict_types=1);

namespace Tests\Acceptance\Energy;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Egy\Application\Services\EnergySimulationRunner;
use Tests\TestCase;

class EnergyDeterministicSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected static ?string $run1StateHash = null;

    /** @var array<string, mixed>|null */
    protected static ?array $run1Metrics = null;

    protected EnergySimulationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->runner = app(EnergySimulationRunner::class);
    }

    public function test_31_day_simulation_run_1_completes_cleanly_and_passes_all_audits(): void
    {
        $result = $this->runner->run(days: 31, metersPerProperty: 10, seed: 12345);

        self::$run1StateHash = $result['state_hash'];
        self::$run1Metrics = $result;

        expect($result['days'])->toBe(31)
            ->and($result['properties_count'])->toBe(5)
            ->and($result['meters_count'])->toBe(50)
            ->and($result['total_readings_ingested'])->toBeGreaterThan(5000)
            ->and($result['duplicate_attempts'])->toBeGreaterThan(0)
            ->and($result['out_of_order_queue_deliveries'])->toBeGreaterThan(0)
            ->and($result['missing_intervals_detected'])->toBeGreaterThan(0)
            ->and($result['rollover_events'])->toBe(2)
            ->and($result['reset_events'])->toBe(1)
            ->and($result['ppa_total_minor'])->toBeGreaterThan(0)
            ->and($result['uc_plans_count'])->toBe(31)
            ->and($result['islanding_events_count'])->toBe(3)
            ->and($result['bess_runs_count'])->toBe(31)
            ->and($result['invoices_count'])->toBe(5)
            ->and($result['total_invoiced_minor'])->toBeGreaterThan(0)
            ->and($result['audit_exit_code'])->toBe(0)
            ->and($result['group_audit_exit_code'])->toBe(0)
            ->and($result['bank_reconcile_exit_code'])->toBe(0)
            ->and($result['chain_valid'])->toBeTrue()
            ->and(strlen($result['state_hash']))->toBe(64);
    }

    public function test_31_day_simulation_run_2_with_identical_seed_produces_identical_state_hash(): void
    {
        $result = $this->runner->run(days: 31, metersPerProperty: 10, seed: 12345);

        expect(self::$run1StateHash)->not->toBeNull()
            ->and($result['state_hash'])->toBe(self::$run1StateHash)
            ->and($result['total_invoiced_minor'])->toBe(self::$run1Metrics['total_invoiced_minor'])
            ->and($result['ppa_total_minor'])->toBe(self::$run1Metrics['ppa_total_minor'])
            ->and($result['total_readings_ingested'])->toBe(self::$run1Metrics['total_readings_ingested'])
            ->and($result['audit_exit_code'])->toBe(0)
            ->and($result['group_audit_exit_code'])->toBe(0)
            ->and($result['bank_reconcile_exit_code'])->toBe(0);
    }

    public function test_simulation_with_different_seed_produces_divergent_state_hash(): void
    {
        $resultDifferent = $this->runner->run(days: 31, metersPerProperty: 10, seed: 99999);

        expect(self::$run1StateHash)->not->toBeNull()
            ->and($resultDifferent['state_hash'])->not->toBe(self::$run1StateHash);
    }
}
