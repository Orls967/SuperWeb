<?php

declare(strict_types=1);

namespace Tests\Acceptance\Media;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Med\Application\Services\MediaSimulationRunner;
use Tests\TestCase;

/**
 * L15.4: 12-Month Media & Creative Ecosystem Deterministic Simulation.
 * Scale: 12 months, 10 studios, 1,500 bookings (>=10% competing rejected),
 * 300 IP assets, 120 licenses (>=20 overlap rejected), 12 settlement periods,
 * 50 campaigns with 2,000,000 impressions (3% duplicates rejected, 1% fraud flagged).
 * Enforces PR3 Determinism: two runs with identical seed produce identical state hash.
 */
class MediaDeterministicSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected static ?string $run1StateHash = null;

    /** @var array<string, mixed>|null */
    protected static ?array $run1Metrics = null;

    protected MediaSimulationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->runner = app(MediaSimulationRunner::class);
    }

    public function test_12_month_media_simulation_run_1_completes_cleanly_and_passes_all_audits(): void
    {
        $result = $this->runner->run(
            months: 12,
            studiosCount: 10,
            bookingsCount: 1500,
            ipAssetsCount: 300,
            licensesCount: 120,
            campaignsCount: 50,
            impressionsCount: 2000000,
            seed: 151515
        );

        self::$run1StateHash = $result['state_hash'];
        self::$run1Metrics = $result;

        expect($result['months'])->toBe(12)
            ->and($result['studios_count'])->toBe(10)
            ->and($result['bookings_count'])->toBe(1500)
            ->and($result['rejected_bookings_count'])->toBeGreaterThanOrEqual(150)
            ->and($result['ip_assets_count'])->toBe(300)
            ->and($result['licenses_count'])->toBe(120)
            ->and($result['rejected_licenses_count'])->toBeGreaterThanOrEqual(20)
            ->and($result['settlement_periods_count'])->toBe(12)
            ->and($result['royalty_statements_count'])->toBeGreaterThan(0)
            ->and($result['total_royalty_distributed_minor'])->toBeGreaterThan(0)
            ->and($result['campaigns_count'])->toBe(50)
            ->and($result['impressions_count'])->toBe(2000000)
            ->and($result['rejected_duplicate_impressions'])->toBeGreaterThanOrEqual(60000)
            ->and($result['fraud_impressions_count'])->toBeGreaterThanOrEqual(20000)
            ->and($result['verified_impressions_count'])->toBe(1980000)
            ->and($result['total_ad_spend_minor'])->toBeGreaterThan(0)
            ->and($result['audit_exit_code'])->toBe(0)
            ->and($result['bank_reconcile_exit_code'])->toBe(0)
            ->and($result['chain_valid'])->toBeTrue()
            ->and(strlen($result['state_hash']))->toBe(64);
    }

    public function test_12_month_media_simulation_run_2_with_identical_seed_produces_identical_state_hash(): void
    {
        $result = $this->runner->run(
            months: 12,
            studiosCount: 10,
            bookingsCount: 1500,
            ipAssetsCount: 300,
            licensesCount: 120,
            campaignsCount: 50,
            impressionsCount: 2000000,
            seed: 151515
        );

        expect(self::$run1StateHash)->not->toBeNull()
            ->and($result['state_hash'])->toBe(self::$run1StateHash)
            ->and($result['total_royalty_distributed_minor'])->toBe(self::$run1Metrics['total_royalty_distributed_minor'])
            ->and($result['total_ad_spend_minor'])->toBe(self::$run1Metrics['total_ad_spend_minor'])
            ->and($result['rejected_bookings_count'])->toBe(self::$run1Metrics['rejected_bookings_count'])
            ->and($result['rejected_licenses_count'])->toBe(self::$run1Metrics['rejected_licenses_count']);
    }

    public function test_12_month_media_simulation_with_divergent_seed_produces_divergent_state_hash(): void
    {
        $result = $this->runner->run(
            months: 12,
            studiosCount: 10,
            bookingsCount: 1500,
            ipAssetsCount: 300,
            licensesCount: 120,
            campaignsCount: 50,
            impressionsCount: 2000000,
            seed: 999999
        );

        expect(self::$run1StateHash)->not->toBeNull()
            ->and($result['state_hash'])->not->toBe(self::$run1StateHash);
    }
}
