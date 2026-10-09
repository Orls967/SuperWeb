<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\LearningSkillIntelligenceService;
use Tests\TestCase;

/**
 * Fase 227 — SDM: Learning Cloud, Academy Scale & Skill Intelligence Tests
 *
 * Covers:
 *  (a) Course publishing and Edge Case 227.7: Version lock per enrollment (immutable during class run)
 *  (b) Course completion with pre/post evaluation effectiveness tracking
 *  (c) Edge Case 227.6: Expired mandatory certification flags assignment hold and blocks role gate
 *  (d) Skill gap assessment computation
 *  (e) Quality audit campus:audit & edu:audit clean with 0 discrepancies
 */
class LearningSkillIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected LearningSkillIntelligenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LearningSkillIntelligenceService::class);
    }

    /**
     * (a) Edge Case 227.7: Content version lock per enrollment.
     */
    public function test_course_version_lock_per_enrollment(): void
    {
        // 1. Publish Version 1.0 of Aviation Safety Course
        $this->service->publishCourse('CRS-AV-101', '1.0', 'Aviation Flight Operations', 'COMPLIANCE', 4.0);

        // 2. Employee enrolls in Version 1.0
        $enrollment = $this->service->enrollEmployee('EMP-PILOT-01', 'CRS-AV-101');
        $this->assertSame('1.0', $enrollment->locked_version);

        // 3. New Version 2.0 is published later
        $this->service->publishCourse('CRS-AV-101', '2.0', 'Aviation Flight Operations (Rev 2)', 'COMPLIANCE', 5.0);

        // 4. Verify existing enrollment remains immutable on Version 1.0
        $freshEnrollment = DB::table('edu_course_enrollments')->where('enrollment_code', $enrollment->enrollment_code)->first();
        $this->assertSame('1.0', $freshEnrollment->locked_version);

        // 5. New enrollee gets Version 2.0
        $newEnrollment = $this->service->enrollEmployee('EMP-PILOT-02', 'CRS-AV-101');
        $this->assertSame('2.0', $newEnrollment->locked_version);
    }

    /**
     * (b) Pre/post test evaluation and course completion (227.3).
     */
    public function test_course_completion_and_effectiveness(): void
    {
        $this->service->publishCourse('CRS-DATA-101', '1.0', 'Data Analytics', 'TECHNICAL', 8.0);
        $enr = $this->service->enrollEmployee('EMP-ANALYST-1', 'CRS-DATA-101');

        $completed = $this->service->completeCourse($enr->enrollment_code, 45.0, 92.5);
        $this->assertSame('COMPLETED', $completed->status);
        $this->assertEquals(45.0, (float) $completed->pre_test_score);
        $this->assertEquals(92.5, (float) $completed->post_test_score);
        $this->assertEquals(100.0, (float) $completed->progress_pct);
    }

    /**
     * (c) Edge Case 227.6: Expired mandatory certification flags assignment hold.
     */
    public function test_mandatory_certification_expiry_and_assignment_hold(): void
    {
        // 1. Valid certification -> Gate cleared
        $futureDate = now()->addMonths(6)->format('Y-m-d');
        $this->service->issueCertification('EMP-DOC-01', 'ICU_LIFE_SUPPORT', $futureDate, true);
        $this->assertTrue($this->service->verifyRoleCertificationGate('EMP-DOC-01', 'ICU_LIFE_SUPPORT'));

        // 2. Expired mandatory certification -> Gate blocked & assignment hold triggered
        $pastDate = now()->subDays(10)->format('Y-m-d');
        $this->service->issueCertification('EMP-MIN-01', 'UNDERGROUND_BLASTING', $pastDate, true);

        $passed = $this->service->verifyRoleCertificationGate('EMP-MIN-01', 'UNDERGROUND_BLASTING');
        $this->assertFalse($passed);

        $cert = DB::table('edu_employee_certifications')
            ->where('employee_id', 'EMP-MIN-01')
            ->where('skill_code', 'UNDERGROUND_BLASTING')
            ->first();

        $this->assertSame('EXPIRED', $cert->status);
        $this->assertTrue((bool) $cert->assignment_hold); // 227.6 Assignment hold triggered
    }

    /**
     * (d) Skill gap assessment (227.2 & 227.5).
     */
    public function test_skill_gap_assessment(): void
    {
        $assessment = $this->service->assessSkillGap('FINANCE_UNIT', 'IFRS_17', 4.5, 3.2);
        $this->assertEquals(1.3, (float) $assessment->gap_score);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_learning_skill_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
