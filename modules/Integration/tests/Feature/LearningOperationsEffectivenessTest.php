<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\LearningOperationsEffectivenessService;
use Tests\TestCase;

class LearningOperationsEffectivenessTest extends TestCase
{
    use RefreshDatabase;

    protected LearningOperationsEffectivenessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LearningOperationsEffectivenessService::class);
    }

    public function test_compliance_assignment_lifecycle_and_blocking(): void
    {
        // 424.1 Create course
        $course = $this->service->createCourse(
            courseCode: 'CRS-AML-CFT-2026',
            title: 'Anti-Money Laundering & Counter-Terrorist Financing Annual Recertification',
            isMandatoryCompliance: true,
            instructorId: 'INST-LEGAL-01',
            instructorQualified: true,
            materialsVersion: '3.1'
        );

        $this->assertEquals('CRS-AML-CFT-2026', $course->course_code);

        // 424.3 Assign compliance training
        $assignment = $this->service->assignComplianceTraining(
            assignmentCode: 'ASG-2026-001',
            courseCode: 'CRS-AML-CFT-2026',
            employeeId: 'EMP-TELLER-01',
            dueDate: '2026-10-15'
        );

        $this->assertEquals('ASG-2026-001', $assignment->assignment_code);
        $this->assertFalse((bool) $assignment->role_activity_blocked);

        // 424.4 Flag overdue -> triggers operational role blocking
        $overdue = $this->service->flagOverdueCompliance('ASG-2026-001');
        $this->assertTrue((bool) $overdue->is_overdue);
        $this->assertTrue((bool) $overdue->role_activity_blocked);

        // Complete training unblocks role activity
        $completed = $this->service->completeCompliance('ASG-2026-001');
        $this->assertTrue((bool) $completed->is_completed);
        $this->assertFalse((bool) $completed->role_activity_blocked);

        // 424.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_unqualified_instructor_blocked_edge_case(): void
    {
        // 424.5 Edge case: assigning unqualified instructor to course is blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('lacks required certifications');

        $this->service->createCourse(
            courseCode: 'CRS-HAZMAT-01',
            title: 'Hazardous Materials Handling Level 4',
            isMandatoryCompliance: true,
            instructorId: 'INST-NOVICE',
            instructorQualified: false // Unqualified!
        );
    }
}
