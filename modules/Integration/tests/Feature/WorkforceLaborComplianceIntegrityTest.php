<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\WorkforceLaborComplianceIntegrityService;
use Tests\TestCase;

class WorkforceLaborComplianceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected WorkforceLaborComplianceIntegrityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkforceLaborComplianceIntegrityService::class);
    }

    public function test_valid_schedule_and_overtime_approval_flow(): void
    {
        // 415.1 Valid schedule with 12 hours rest before shift
        $sched = $this->service->createSchedule(
            scheduleCode: 'SCHED-2026-10-01-EMP01',
            employeeId: 'EMP-4401',
            shiftDate: '2026-10-01',
            scheduledHours: 8,
            restHoursBeforeShift: 12,
            credentialsValid: true
        );

        $this->assertEquals('SCHED-2026-10-01-EMP01', $sched->schedule_code);
        $this->assertFalse((bool) $sched->rule_violation_detected);

        // Publish schedule
        $published = $this->service->publishSchedule('SCHED-2026-10-01-EMP01');
        $this->assertTrue((bool) $published->is_published);

        // 415.2 & 415.3 Time exception with 2 hours overtime
        $exc = $this->service->recordTimeException(
            exceptionCode: 'EXC-2026-001',
            scheduleCode: 'SCHED-2026-10-01-EMP01',
            actualHoursWorked: 10,
            hourlyRate: 100000.00
        );

        $this->assertEquals(2, $exc->overtime_hours);
        $this->assertEquals(300000.00, (float) $exc->overtime_premium_amount); // 2 * 100k * 1.5
        $this->assertFalse((bool) $exc->is_approved_by_manager);

        // Manager approves exception
        $approved = $this->service->approveTimeException('EXC-2026-001', 'HCM Operations Lead');
        $this->assertTrue((bool) $approved->is_approved_by_manager);

        // 415.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_labor_rule_violation_blocks_schedule_publish_edge_case(): void
    {
        // 415.1 & 415.4 Edge case: rest < 11 hours (e.g. turnaround quick shift of 8 hours rest)
        $this->service->createSchedule(
            scheduleCode: 'SCHED-ILLEGAL-QUICK-TURN',
            employeeId: 'EMP-9902',
            shiftDate: '2026-10-02',
            scheduledHours: 8,
            restHoursBeforeShift: 7, // Violates mandatory 11h rest rule!
            credentialsValid: true
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Schedule violates mandatory labor regulations');

        $this->service->publishSchedule('SCHED-ILLEGAL-QUICK-TURN');
    }
}
