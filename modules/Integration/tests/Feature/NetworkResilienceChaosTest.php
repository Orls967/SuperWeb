<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\NetworkResilienceChaosService;
use Tests\TestCase;

class NetworkResilienceChaosTest extends TestCase
{
    use RefreshDatabase;

    protected NetworkResilienceChaosService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NetworkResilienceChaosService::class);
    }

    public function test_critical_dependency_n_minus_one_and_cost_tradeoff(): void
    {
        // 1. Valid N-1 dependency with approved trade-off cost (308.1 & 308.6)
        $dep = $this->service->registerCriticalDependency(
            dependencyCode: 'DEP-PRIMARY-AZ-01',
            component: 'CLOUD_REGION_PRIMARY',
            hasNMinusOneFailover: true,
            redundancyCostUsd: 150000.0,
            tradeOffApproved: true
        );
        $this->assertTrue((bool) $dep->has_n_minus_one_failover);
        $this->assertTrue((bool) $dep->trade_off_cost_approved);

        // 2. High investment without approval is rejected (308.6 Risk)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Resilience cost trade-off unapproved');
        $this->service->registerCriticalDependency('DEP-EXPENSIVE', 'DC', true, 200000.0, false);
    }

    public function test_chaos_game_day_rto_and_blocker_logging(): void
    {
        // 1. Fast recovery within RTO (15s <= 60s) passes (308.2 & 308.4)
        $passed = $this->service->executeChaosExercise('GAME-NODE-KILL-01', 'NODE_KILL', 15, 60);
        $this->assertTrue((bool) $passed->rto_target_met);
        $this->assertFalse((bool) $passed->is_blocker_finding_logged);

        // 2. Slow recovery exceeding RTO (85s > 60s) logs blocker finding (308.5 Edge Case)
        try {
            $this->service->executeChaosExercise('GAME-REGION-BLACKOUT-02', 'REGION_BLACKOUT', 85, 60);
            $this->fail('Expected exception for failed chaos exercise');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('registered as blocker finding', $e->getMessage());
        }

        $failedRecord = DB::table('network_chaos_game_days')->where('exercise_code', 'GAME-REGION-BLACKOUT-02')->first();
        $this->assertFalse((bool) $failedRecord->rto_target_met);
        $this->assertTrue((bool) $failedRecord->is_blocker_finding_logged);
    }

    public function test_risk_resilience_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerCriticalDependency('DEP-AUD', 'COMPONENT', true, 1000.0, true);
        $this->service->executeChaosExercise('GAME-AUD', 'TEST', 10, 60);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: critical dependency without N-1
        DB::table('network_redundancy_dependencies')->insert([
            'dependency_code' => 'DEP-SPOF-DISCREPANCY',
            'critical_component' => 'CORE_DATABASE_MASTER',
            'has_n_minus_one_failover' => false, // Discrepancy!
            'redundancy_investment_cost_usd' => 0.0,
            'trade_off_cost_approved' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
