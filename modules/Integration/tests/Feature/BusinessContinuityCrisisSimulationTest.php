<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\BusinessContinuityCrisisSimulationService;
use Tests\TestCase;

class BusinessContinuityCrisisSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessContinuityCrisisSimulationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BusinessContinuityCrisisSimulationService::class);
    }

    public function test_bcp_exercise_outcome_and_crisis_comms_flow(): void
    {
        // 452.1 Schedule annual cyber ransomware failover simulation (Target RTO: 60 minutes)
        $ex = $this->service->scheduleExercise(
            exerciseCode: 'SIM-CYBER-2026',
            scenarioType: 'cyber',
            targetRtoMinutes: 60.00
        );

        $this->assertEquals('SIM-CYBER-2026', $ex->exercise_code);
        $this->assertEquals('scheduled', $ex->status);

        // 452.2 & 452.4 Complete exercise within RTO (48 minutes)
        $outcome = $this->service->recordExerciseOutcome(
            exerciseCode: 'SIM-CYBER-2026',
            actualRecoveryMinutes: 48.00,
            afterActionReview: 'Primary DB safely isolated, read-replicas promoted, service restored in 48 mins'
        );

        $this->assertEquals('completed', $outcome->status);
        $this->assertTrue((bool) $outcome->exercise_objectives_met);
        $this->assertFalse((bool) $outcome->mandatory_re_drill_required);

        // 452.3 Draft and release crisis communication with spokesperson sign-off
        $this->service->draftCrisisComms('COMM-PRESS-CYBER', 'SIM-CYBER-2026', 'media', true);

        $released = $this->service->releaseCrisisComms('COMM-PRESS-CYBER', true);
        $this->assertTrue((bool) $released->is_released);
        $this->assertTrue((bool) $released->spokesperson_chain_approved);

        // 452.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_exceeded_rto_redrill_and_unapproved_comms_blocked_edge_cases(): void
    {
        // 452.5 Edge case: Exceeded RTO flags exercise failure and mandates re-drill
        $this->service->scheduleExercise('SIM-POWER-BLACKOUT', 'utility_outage', 30.00);

        // Actual recovery took 45 minutes (> 30 mins)
        $failed = $this->service->recordExerciseOutcome(
            exerciseCode: 'SIM-POWER-BLACKOUT',
            actualRecoveryMinutes: 45.00,
            afterActionReview: 'Backup generator ATS transfer switch delayed by 15 mins'
        );

        $this->assertEquals('failed_requires_redrill', $failed->status);
        $this->assertFalse((bool) $failed->exercise_objectives_met);
        $this->assertTrue((bool) $failed->mandatory_re_drill_required);

        // 452.6 Risk: Unapproved crisis comms cannot be broadcast
        $this->service->draftCrisisComms('COMM-LEAKED-MEMO', 'SIM-POWER-BLACKOUT', 'customers');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Crisis communication release requires formal executive spokesperson sign-off');

        $this->service->releaseCrisisComms('COMM-LEAKED-MEMO', false);
    }
}
