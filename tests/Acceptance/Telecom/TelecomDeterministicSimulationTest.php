<?php

declare(strict_types=1);

namespace Tests\Acceptance\Telecom;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tlx\Application\Services\TelecomSimulationRunner;
use Tests\TestCase;

/**
 * L14.4: 31-Day Telecom & Data Center Ecosystem Deterministic Simulation.
 * Scale: 31 days, 3 entities, 5,000 devices, 200,000 CDRs, 2 DCs, 500 topology elements.
 * Enforces PR3 Determinism: two runs with identical seed produce identical state hash and metrics.
 */
class TelecomDeterministicSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected static ?string $run1StateHash = null;

    /** @var array<string, mixed>|null */
    protected static ?array $run1Metrics = null;

    protected TelecomSimulationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->runner = app(TelecomSimulationRunner::class);
    }

    public function test_31_day_telecom_simulation_run_1_completes_cleanly_and_passes_all_audits(): void
    {
        $result = $this->runner->run(days: 31, devicesCount: 5000, cdrsCount: 200000, seed: 141414);

        self::$run1StateHash = $result['state_hash'];
        self::$run1Metrics = $result;

        expect($result['days'])->toBe(31)
            ->and($result['entities_count'])->toBe(3)
            ->and($result['devices_count'])->toBe(5000)
            ->and($result['active_devices_count'])->toBeGreaterThan(4800)
            ->and($result['sim_profiles_count'])->toBe(5000)
            ->and($result['cdrs_count'])->toBe(200000)
            ->and($result['total_rated_bytes'])->toBeGreaterThan(100000000)
            ->and($result['data_centers_count'])->toBe(2)
            ->and($result['colocation_contracts_count'])->toBe(2)
            ->and($result['power_readings_count'])->toBe(62)
            ->and($result['topology_elements_count'])->toBe(500)
            ->and($result['noc_alarms_count'])->toBe(60)
            ->and($result['suppressed_alarms_count'])->toBeGreaterThan(0)
            ->and($result['noc_tickets_count'])->toBeGreaterThan(0)
            ->and($result['connectivity_invoiced_minor'])->toBeGreaterThan(0)
            ->and($result['colocation_invoiced_minor'])->toBeGreaterThan(0)
            ->and($result['audit_exit_code'])->toBe(0)
            ->and($result['bank_reconcile_exit_code'])->toBe(0)
            ->and($result['chain_valid'])->toBeTrue()
            ->and(strlen($result['state_hash']))->toBe(64);
    }

    public function test_31_day_telecom_simulation_run_2_with_identical_seed_produces_identical_state_hash(): void
    {
        $result = $this->runner->run(days: 31, devicesCount: 5000, cdrsCount: 200000, seed: 141414);

        expect(self::$run1StateHash)->not->toBeNull()
            ->and($result['state_hash'])->toBe(self::$run1StateHash)
            ->and($result['devices_count'])->toBe(self::$run1Metrics['devices_count'])
            ->and($result['cdrs_count'])->toBe(self::$run1Metrics['cdrs_count'])
            ->and($result['total_rated_bytes'])->toBe(self::$run1Metrics['total_rated_bytes'])
            ->and($result['connectivity_invoiced_minor'])->toBe(self::$run1Metrics['connectivity_invoiced_minor'])
            ->and($result['colocation_invoiced_minor'])->toBe(self::$run1Metrics['colocation_invoiced_minor'])
            ->and($result['audit_exit_code'])->toBe(0)
            ->and($result['bank_reconcile_exit_code'])->toBe(0);
    }

    public function test_telecom_simulation_run_with_different_seed_produces_divergent_hash(): void
    {
        $result = $this->runner->run(days: 31, devicesCount: 5000, cdrsCount: 200000, seed: 99999);

        expect(self::$run1StateHash)->not->toBeNull()
            ->and($result['state_hash'])->not->toBe(self::$run1StateHash)
            ->and($result['audit_exit_code'])->toBe(0)
            ->and($result['bank_reconcile_exit_code'])->toBe(0);
    }
}
