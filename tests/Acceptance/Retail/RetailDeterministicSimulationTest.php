<?php

declare(strict_types=1);

namespace Tests\Acceptance\Retail;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Ret\Application\Services\RetailSimulationRunner;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * L17.4: 60-Day Omnichannel Retail & Marketplace Deterministic Simulation.
 * Enforces PR3 Determinism: two runs with identical seed produce identical state hash and metrics.
 */
class RetailDeterministicSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected static ?string $smallSeedRun1Hash = null;

    protected static ?string $run1StateHash = null;

    /** @var array<string, mixed>|null */
    protected static ?array $run1Metrics = null;

    protected RetailSimulationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->runner = app(RetailSimulationRunner::class);
    }

    public function test_deterministic_small_seed_simulation_run_1(): void
    {
        $result = $this->runner->run(days: 2, seed: 171717);

        self::$smallSeedRun1Hash = $result['state_hash'];

        expect($result['state_hash'])->toBeString()
            ->and(strlen($result['state_hash']))->toBe(64)
            ->and($result['chain_valid'])->toBeTrue()
            ->and($result['audit_exit_code'])->toBe(0);
    }

    public function test_deterministic_small_seed_simulation_run_2_with_identical_seed_produces_identical_hash(): void
    {
        $result = $this->runner->run(days: 2, seed: 171717);

        expect($result['state_hash'])->toBe(self::$smallSeedRun1Hash)
            ->and($result['chain_valid'])->toBeTrue()
            ->and($result['audit_exit_code'])->toBe(0);
    }

    #[Group('skala')]
    public function test_60_day_omnichannel_retail_simulation_run_1_completes_cleanly_and_passes_all_audits(): void
    {
        $result = $this->runner->run(days: 60, seed: 171717);

        self::$run1StateHash = $result['state_hash'];
        self::$run1Metrics = $result;

        expect($result['days'])->toBe(60)
            ->and($result['catalog_skus_count'])->toBe(200)
            ->and($result['dark_stores_count'])->toBe(20)
            ->and($result['crowd_drivers_count'])->toBe(10)
            ->and($result['total_checkouts'])->toBe(6000)
            ->and($result['dark_store_orders'])->toBe(3600)
            ->and($result['regular_orders'])->toBe(2400)
            ->and($result['map_violations_caught'])->toBe(2)
            ->and($result['reusable_deposits_issued'])->toBe(1800)
            ->and($result['deposits_refunded'])->toBeGreaterThan(0)
            ->and($result['deposits_forfeited'])->toBeGreaterThan(0)
            ->and($result['cashback_earned_total'])->toBeGreaterThan(0)
            ->and($result['cashback_expired_breakage'])->toBe(1)
            ->and($result['sla_breaches_compensated'])->toBeGreaterThan(0)
            ->and($result['seller_settlements_count'])->toBeGreaterThan(0)
            ->and($result['settled_gmv_minor'])->toBeGreaterThan(0)
            ->and($result['refunds_processed'])->toBeGreaterThan(0)
            ->and($result['audit_exit_code'])->toBe(0)
            ->and($result['bank_reconcile_exit_code'])->toBe(0)
            ->and($result['chain_valid'])->toBeTrue()
            ->and(strlen($result['state_hash']))->toBe(64);
    }

    #[Group('skala')]
    public function test_60_day_omnichannel_retail_simulation_run_2_with_identical_seed_produces_identical_state_hash(): void
    {
        $result = $this->runner->run(days: 60, seed: 171717);

        expect(self::$run1StateHash)->not->toBeNull()
            ->and($result['state_hash'])->toBe(self::$run1StateHash)
            ->and($result['total_checkouts'])->toBe(self::$run1Metrics['total_checkouts'])
            ->and($result['dark_store_orders'])->toBe(self::$run1Metrics['dark_store_orders'])
            ->and($result['regular_orders'])->toBe(self::$run1Metrics['regular_orders'])
            ->and($result['reusable_deposits_issued'])->toBe(self::$run1Metrics['reusable_deposits_issued'])
            ->and($result['deposits_refunded'])->toBe(self::$run1Metrics['deposits_refunded'])
            ->and($result['deposits_forfeited'])->toBe(self::$run1Metrics['deposits_forfeited'])
            ->and($result['cashback_earned_total'])->toBe(self::$run1Metrics['cashback_earned_total'])
            ->and($result['seller_settlements_count'])->toBe(self::$run1Metrics['seller_settlements_count'])
            ->and($result['settled_gmv_minor'])->toBe(self::$run1Metrics['settled_gmv_minor'])
            ->and($result['refunds_processed'])->toBe(self::$run1Metrics['refunds_processed'])
            ->and($result['audit_exit_code'])->toBe(0)
            ->and($result['bank_reconcile_exit_code'])->toBe(0);
    }

    #[Group('skala')]
    public function test_60_day_simulation_run_with_different_seed_produces_divergent_hash(): void
    {
        $result = $this->runner->run(days: 60, seed: 99999);

        expect(self::$run1StateHash)->not->toBeNull()
            ->and($result['state_hash'])->not->toBe(self::$run1StateHash);
    }
}
