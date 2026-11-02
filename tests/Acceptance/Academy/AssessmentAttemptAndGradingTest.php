<?php

declare(strict_types=1);

namespace Tests\Acceptance\Academy;

use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Edu\Application\Actions\EvaluateProctoredExamAttemptAction;
use Modules\Edu\Domain\Models\EduExamAttempt;
use Tests\TestCase;

class AssessmentAttemptAndGradingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_proctored_exam_attempt_creates_append_only_record(): void
    {
        $action = app(EvaluateProctoredExamAttemptAction::class);
        $attempt = $action->execute([
            'student_party_uuid' => 'student-exam-01',
            'enrollment_code' => 'ENR-EXAM-01',
            'exam_title' => 'K3 Industrial Safety Exam',
            'score' => 85,
            'passing_score' => 75,
            'proctor_signature' => 'PROCTOR_STAMP_ALPHA',
            'idempotency_key' => 'edu:exam:att:01',
        ]);

        $this->assertInstanceOf(EduExamAttempt::class, $attempt);
        $this->assertTrue($attempt->is_passed);
        $this->assertEquals(85, $attempt->score);
        $this->assertEquals('COMPLETED', $attempt->status);
        $this->assertNotEmpty($attempt->proctor_verification_hash);
    }

    public function test_score_below_passing_score_is_marked_failed(): void
    {
        $action = app(EvaluateProctoredExamAttemptAction::class);
        $attempt = $action->execute([
            'student_party_uuid' => 'student-exam-02',
            'enrollment_code' => 'ENR-EXAM-02',
            'exam_title' => 'High Voltage Power Transmission Exam',
            'score' => 68,
            'passing_score' => 75,
            'proctor_signature' => 'PROCTOR_BETA',
            'idempotency_key' => 'edu:exam:att:02',
        ]);

        $this->assertFalse($attempt->is_passed);
        $this->assertEquals(68, $attempt->score);
    }

    public function test_score_exact_passing_boundary_is_marked_passed(): void
    {
        $action = app(EvaluateProctoredExamAttemptAction::class);
        $attempt = $action->execute([
            'student_party_uuid' => 'student-exam-03',
            'enrollment_code' => 'ENR-EXAM-03',
            'exam_title' => 'Telecom Tower Climbing Exam',
            'score' => 75,
            'passing_score' => 75,
            'proctor_signature' => 'PROCTOR_GAMMA',
            'idempotency_key' => 'edu:exam:att:03',
        ]);

        $this->assertTrue($attempt->is_passed);
        $this->assertEquals(75, $attempt->score);
    }

    public function test_exam_attempt_requires_idempotency_key(): void
    {
        $action = app(EvaluateProctoredExamAttemptAction::class);

        $this->expectException(InvalidArgumentException::class);
        $action->execute([
            'student_party_uuid' => 'student-no-key',
            'enrollment_code' => 'ENR-NO-KEY',
            'exam_title' => 'Unkeyed Exam',
            'score' => 80,
        ]);
    }

    public function test_exam_attempt_model_guard_rejects_update(): void
    {
        $action = app(EvaluateProctoredExamAttemptAction::class);
        $attempt = $action->execute([
            'student_party_uuid' => 'student-guard-01',
            'enrollment_code' => 'ENR-GUARD-01',
            'exam_title' => 'Immutable Exam Facts',
            'score' => 80,
            'passing_score' => 75,
            'idempotency_key' => 'edu:exam:guard:01',
        ]);

        $this->expectException(DomainException::class);
        $attempt->update(['score' => 99]);
    }

    public function test_exam_attempt_model_guard_rejects_delete(): void
    {
        $action = app(EvaluateProctoredExamAttemptAction::class);
        $attempt = $action->execute([
            'student_party_uuid' => 'student-guard-02',
            'enrollment_code' => 'ENR-GUARD-02',
            'exam_title' => 'Immutable Exam Facts 2',
            'score' => 80,
            'passing_score' => 75,
            'idempotency_key' => 'edu:exam:guard:02',
        ]);

        $this->expectException(DomainException::class);
        $attempt->delete();
    }

    public function test_multiple_attempts_allowed_with_separate_idempotency_keys(): void
    {
        $action = app(EvaluateProctoredExamAttemptAction::class);

        // Attempt 1: Failed
        $a1 = $action->execute([
            'student_party_uuid' => 'student-retry',
            'enrollment_code' => 'ENR-RETRY',
            'exam_title' => 'Certification Exam',
            'score' => 60,
            'passing_score' => 75,
            'idempotency_key' => 'edu:exam:retry:01',
        ]);

        // Attempt 2: Passed
        $a2 = $action->execute([
            'student_party_uuid' => 'student-retry',
            'enrollment_code' => 'ENR-RETRY',
            'exam_title' => 'Certification Exam',
            'score' => 90,
            'passing_score' => 75,
            'idempotency_key' => 'edu:exam:retry:02',
        ]);

        $this->assertFalse($a1->is_passed);
        $this->assertTrue($a2->is_passed);
        $this->assertNotEquals($a1->id, $a2->id);
    }
}
