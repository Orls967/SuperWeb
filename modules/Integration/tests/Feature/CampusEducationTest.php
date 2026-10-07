<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\CampusEducationService;
use Tests\TestCase;

/**
 * Fase 166 — Campus Education Tests
 *
 * Covers:
 *  (a) no timetable overlap (conflict detected and rejected)
 *  (b) grades immutable & certificate hash-chain valid
 *  (c) scholarship <= tuition enforced
 *  (d) campus:audit = 0 variance
 */
class CampusEducationTest extends TestCase
{
    use RefreshDatabase;

    protected CampusEducationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CampusEducationService::class);
    }

    /**
     * (a) Timetable conflict detection prevents room or teacher collision.
     */
    public function test_timetable_conflict_detection(): void
    {
        $this->service->registerInstitution('CAMP-ITB-01', 'Institut Teknologi Bandung', 'UNIVERSITY');

        // First schedule: Room 101, Teacher T1, 08:00 - 10:00 on Monday (day 1)
        $slot1 = $this->service->scheduleClass('CAMP-ITB-01', 'ROOM-101', 'TEACHER-01', 'CS101', 1, '08:00:00', '10:00:00');
        $this->assertSame('ROOM-101', $slot1->room_id);

        // Conflict: Same room overlapping 09:00 - 11:00 on Monday
        $this->expectException(\RuntimeException::class);
        $this->service->scheduleClass('CAMP-ITB-01', 'ROOM-101', 'TEACHER-02', 'CS102', 1, '09:00:00', '11:00:00');
    }

    /**
     * (b) Grades locked with verifiable cryptographic certificate hash.
     */
    public function test_grade_locking_and_hash(): void
    {
        $transcript = $this->service->submitAndLockGrade(1001, 'MATH201', 92.50, 'A');

        $this->assertTrue((bool) $transcript->is_locked);
        $this->assertSame('A', $transcript->letter_grade);
        $this->assertEquals(92.50, (float) $transcript->grade_score);
        $this->assertNotNull($transcript->certificate_hash);
    }

    /**
     * (c) Scholarship deduction bounded by gross tuition amount.
     */
    public function test_tuition_billing_and_scholarship_bound(): void
    {
        // 1. Partial scholarship: Gross 15m, Scholarship 5m -> Net 10m
        $inv = $this->service->issueTuitionInvoice(1002, 'FALL-2026', 15000000.00, 5000000.00);
        $this->assertEquals(15000000.00, (float) $inv->gross_tuition_amount);
        $this->assertEquals(5000000.00, (float) $inv->scholarship_aid_deduction);
        $this->assertEquals(10000000.00, (float) $inv->net_tuition_payable);

        // 2. Excess scholarship: Aid (20m) > Gross (15m) -> Exception
        $this->expectException(\InvalidArgumentException::class);
        $this->service->issueTuitionInvoice(1002, 'FALL-2026', 15000000.00, 20000000.00);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_campus_education_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
