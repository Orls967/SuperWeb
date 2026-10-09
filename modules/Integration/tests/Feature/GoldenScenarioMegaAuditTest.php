<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\GoldenScenarioMegaAuditService;
use Tests\TestCase;

class GoldenScenarioMegaAuditTest extends TestCase
{
    use RefreshDatabase;

    protected GoldenScenarioMegaAuditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GoldenScenarioMegaAuditService::class);
    }

    public function test_180_day_golden_scenario_determinism_and_identical_fingerprints(): void
    {
        // 1. First 180-day golden run (255.1 & 255.7)
        $run1 = $this->service->run180DayGoldenScenario(seed: 20261008);
        $this->assertEquals('COMPLETED', $run1->status);
        $this->assertEquals(180, (int) $run1->days_simulated);
        $this->assertEquals(80, (int) $run1->total_audits_passed_count); // 255.2
        $this->assertEquals(0, (int) $run1->discrepancy_count);
        $this->assertEquals(0, (int) $run1->dlq_count_at_end); // 255.8
        $this->assertNotEmpty($run1->cryptographic_fingerprint);

        // 2. Second 180-day golden run with same seed must yield identical fingerprint (255.5 & 255.7)
        $run2 = $this->service->run180DayGoldenScenario(seed: 20261008);
        $this->assertEquals($run1->cryptographic_fingerprint, $run2->cryptographic_fingerprint);
    }

    public function test_mid_simulation_audit_failure_halts_immediately(): void
    {
        // Edge case 255.6: Mid-simulation audit failure halts execution immediately
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Simulation halted: Mid-simulation audit failure detected');

        $this->service->run180DayGoldenScenario(seed: 9999, simulateMidAuditFailure: true);
    }

    public function test_compound_crisis_mega_scenario_recovery(): void
    {
        $run = $this->service->run180DayGoldenScenario(seed: 12345);

        // Crisis mega-scenario (255.3)
        $crisis = $this->service->runCrisisMegaScenario((int) $run->id);

        $this->assertTrue((bool) $crisis->continuity_plan_activated);
        $this->assertTrue((bool) $crisis->recovery_successful);
        $this->assertTrue((bool) $crisis->zero_discrepancy_verified);
    }

    public function test_ma_3_module_acquisition_scenario(): void
    {
        $run = $this->service->run180DayGoldenScenario(seed: 54321);

        // M&A acquisition of 3 external modules (255.4)
        $ma = $this->service->runMaAcquisitionScenario((int) $run->id, [
            'MODULE_EXTERNAL_LOGISTICS',
            'MODULE_EXTERNAL_FINTECH_PAY',
            'MODULE_EXTERNAL_COLD_CHAIN',
        ]);

        $this->assertEquals('COMPLETE', $ma->backfill_status);
        $this->assertEquals('CLEAN_ZERO_DISCREPANCY', $ma->consolidation_audit);
        $this->assertFalse((bool) $ma->is_leak_detected);
    }

    public function test_mega_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $run = $this->service->run180DayGoldenScenario(seed: 7777);
        $this->service->runCrisisMegaScenario((int) $run->id);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: completed run with DLQ leak
        DB::table('mega_simulation_runs')->insert([
            'sim_run_code' => 'SIM-DLQ-LEAK',
            'sim_type' => 'GOLDEN_30_LINE',
            'days_simulated' => 180,
            'seed_value' => 8888,
            'cryptographic_fingerprint' => 'HASH',
            'total_audits_passed_count' => 80,
            'discrepancy_count' => 0,
            'dlq_count_at_end' => 15, // Discrepancy!
            'status' => 'COMPLETED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
