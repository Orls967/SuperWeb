<?php

declare(strict_types=1);

namespace Tests\Acceptance\Academy;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Edu\Application\Services\AcademySimulationRunner;
use Tests\TestCase;

/**
 * L16.4: 6-Month Academy & Talent Ecosystem Deterministic Simulation.
 * Enforces PR3 Determinism: two runs with identical seed produce identical state hash and metrics.
 */
class AcademyDeterministicSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected static ?string $run1StateHash = null;

    /** @var array<string, mixed>|null */
    protected static ?array $run1Metrics = null;

    protected AcademySimulationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->runner = app(AcademySimulationRunner::class);
    }

    public function test_6_month_academy_simulation_run_1_completes_cleanly_and_passes_all_audits(): void
    {
        $result = $this->runner->run(months: 6, seed: 161616);

        self::$run1StateHash = $result['state_hash'];
        self::$run1Metrics = $result;

        expect($result['months'])->toBe(6)
            ->and($result['programs_count'])->toBe(40)
            ->and($result['cohorts_count'])->toBe(200)
            ->and($result['corporate_quotas_allocated'])->toBe(5000)
            ->and($result['total_enrollments'])->toBe(8000)
            ->and($result['corporate_enrollments'])->toBe(5000)
            ->and($result['direct_enrollments'])->toBe(3000)
            ->and($result['total_exam_attempts'])->toBe(20000)
            ->and($result['passed_exam_attempts'])->toBeGreaterThan(9000)
            ->and($result['failed_exam_attempts'])->toBeGreaterThan(4000)
            ->and($result['certificates_issued'])->toBeGreaterThan(5000)
            ->and($result['talent_matches_placed'])->toBeGreaterThan(0)
            ->and($result['headhunter_escrows_held'])->toBeGreaterThan(0)
            ->and($result['headhunter_escrows_captured'])->toBeGreaterThan(0)
            ->and($result['total_tuition_billed_minor'])->toBeGreaterThan(0)
            ->and($result['total_headhunter_fees_captured_minor'])->toBeGreaterThan(0)
            ->and($result['audit_exit_code'])->toBe(0)
            ->and($result['bank_reconcile_exit_code'])->toBe(0)
            ->and($result['chain_valid'])->toBeTrue()
            ->and(strlen($result['state_hash']))->toBe(64);
    }

    public function test_6_month_academy_simulation_run_2_with_identical_seed_produces_identical_state_hash(): void
    {
        $result = $this->runner->run(months: 6, seed: 161616);

        expect(self::$run1StateHash)->not->toBeNull()
            ->and($result['state_hash'])->toBe(self::$run1StateHash)
            ->and($result['total_enrollments'])->toBe(self::$run1Metrics['total_enrollments'])
            ->and($result['corporate_enrollments'])->toBe(self::$run1Metrics['corporate_enrollments'])
            ->and($result['direct_enrollments'])->toBe(self::$run1Metrics['direct_enrollments'])
            ->and($result['total_exam_attempts'])->toBe(self::$run1Metrics['total_exam_attempts'])
            ->and($result['passed_exam_attempts'])->toBe(self::$run1Metrics['passed_exam_attempts'])
            ->and($result['failed_exam_attempts'])->toBe(self::$run1Metrics['failed_exam_attempts'])
            ->and($result['certificates_issued'])->toBe(self::$run1Metrics['certificates_issued'])
            ->and($result['talent_matches_placed'])->toBe(self::$run1Metrics['talent_matches_placed'])
            ->and($result['headhunter_escrows_held'])->toBe(self::$run1Metrics['headhunter_escrows_held'])
            ->and($result['headhunter_escrows_captured'])->toBe(self::$run1Metrics['headhunter_escrows_captured'])
            ->and($result['total_tuition_billed_minor'])->toBe(self::$run1Metrics['total_tuition_billed_minor'])
            ->and($result['audit_exit_code'])->toBe(0)
            ->and($result['bank_reconcile_exit_code'])->toBe(0);
    }

    public function test_6_month_simulation_run_with_different_seed_produces_divergent_hash(): void
    {
        $result = $this->runner->run(months: 6, seed: 99999);

        expect(self::$run1StateHash)->not->toBeNull()
            ->and($result['state_hash'])->not->toBe(self::$run1StateHash)
            ->and($result['audit_exit_code'])->toBe(0)
            ->and($result['bank_reconcile_exit_code'])->toBe(0);
    }
}
