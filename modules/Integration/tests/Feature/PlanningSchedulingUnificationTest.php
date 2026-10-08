<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PlanningSchedulingUnificationService;
use Tests\TestCase;

class PlanningSchedulingUnificationTest extends TestCase
{
    use RefreshDatabase;

    protected PlanningSchedulingUnificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlanningSchedulingUnificationService::class);
    }

    public function test_unified_plan_creation_and_signed_off_revision_archival(): void
    {
        // 1. Initial signed-off plan (277.1 & 277.5)
        $plan = $this->service->createUnifiedPlan(
            planCode: 'PLAN-MINING-2026-M11',
            domainLine: 'MINING',
            planningPeriod: '2026-M11',
            demandUnits: 500000.0,
            capacityHours: 720.0,
            workforceHeadcount: 1250.0,
            financialBudgetUsd: 15000000.0,
            isSignedOff: true
        );
        $this->assertEquals(1, (int) $plan->version);
        $this->assertFalse((bool) $plan->is_archived);

        // 2. Modifying signed-off plan archives old version and creates approved revision (277.5 Edge Case)
        $revised = $this->service->reviseSignedOffPlan('PLAN-MINING-2026-M11', 550000.0, 16200000.0);
        $this->assertEquals(2, (int) $revised->version);
        $this->assertEquals('PLAN-MINING-2026-M11-V2', $revised->plan_code);
        $this->assertTrue((bool) $revised->is_signed_off);

        $oldPlan = DB::table('operations_unified_plans')->where('plan_code', 'PLAN-MINING-2026-M11')->first();
        $this->assertTrue((bool) $oldPlan->is_archived);
    }

    public function test_finite_scheduling_100_percent_feasibility_no_overbooking(): void
    {
        // 1. Register 12-hour machine capacity (277.2 & 277.4)
        $this->service->registerResourceSchedule(
            scheduleCode: 'SCHED-CRANE-01',
            resourceId: 'HARBOR_CRANE_01',
            capacityLimitHours: 12.0,
            scheduleDate: now()->toDateString()
        );

        // 2. Valid booking of 8 hours succeeds
        $booked = $this->service->bookScheduleHours('SCHED-CRANE-01', 8.0);
        $this->assertEquals(8.0, (float) $booked->booked_hours);

        // 3. Overbooking attempt exceeding capacity (8 + 6 = 14 > 12) strictly rejected (277.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds hard capacity limit');
        $this->service->bookScheduleHours('SCHED-CRANE-01', 6.0);
    }

    public function test_reschedule_respects_hard_constraints(): void
    {
        $this->service->registerResourceSchedule('SCHED-TRUCK-02', 'HAUL_TRUCK_02', 10.0, now()->toDateString());
        $this->service->bookScheduleHours('SCHED-TRUCK-02', 5.0);

        // 1. Valid reschedule under capacity succeeds (277.2 & 277.6)
        $rescheduled = $this->service->rescheduleWithHardConstraints('SCHED-TRUCK-02', 'MAINTENANCE_BUFFER', 7.5);
        $this->assertTrue((bool) $rescheduled->hard_constraint_respected);

        // 2. Reschedule violating hard working-hour / permit constraint rejected (277.6)
        try {
            $this->service->rescheduleWithHardConstraints('SCHED-TRUCK-02', 'OVERTIME_RUSH', 9.0, violatesHardConstraint: true);
            $this->fail('Expected exception for hard constraint violation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('violates hard operational constraints', $e->getMessage());
        }
    }

    public function test_planning_scheduling_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->createUnifiedPlan('PLAN-AUD', 'LINE', '2026-M11', 100.0, 10.0, 10.0, 1000.0);
        $this->service->registerResourceSchedule('SCHED-AUD', 'RES', 10.0, now()->toDateString());
        $this->service->bookScheduleHours('SCHED-AUD', 5.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: overbooked schedule
        DB::table('operations_finite_schedules')->insert([
            'schedule_code' => 'SCHED-OVERBOOK-BREACH',
            'resource_id' => 'RES_ROGUE',
            'capacity_limit_hours' => 8.0,
            'booked_hours' => 15.0, // Discrepancy: overbooked!
            'schedule_date' => now()->toDateString(),
            'is_overbooked' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
