<?php

declare(strict_types=1);

namespace Tests\Acceptance\Academy;

use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Edu\Application\Actions\IssueVerifiedCompetencyCertificateAction;
use Modules\Edu\Domain\Exceptions\ExamNotPassedException;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduExamAttempt;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Shared\Domain\Numerics\CanonicalHashChain;
use Tests\TestCase;

class GaplessCertificateAndHashChainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_passed_exam_attempt_allows_certificate_issuance(): void
    {
        $program = $this->createProgram('PRG-WELD', 'Advanced Robotic Welding');
        $cohort = $this->createCohort($program);
        $enrollment = $this->createEnrollment($cohort, 'student-weld-01');
        $attempt = $this->createExamAttempt($enrollment, 'student-weld-01', score: 90, passed: true);

        $action = app(IssueVerifiedCompetencyCertificateAction::class);
        $cert = $action->execute([
            'enrollment_id' => $enrollment->id,
            'exam_attempt_id' => $attempt->id,
            'skill_competency_code' => 'k3_loto',
            'idempotency_key' => 'edu:cert:issue:01',
        ]);

        $this->assertInstanceOf(EduCertificate::class, $cert);
        $this->assertEquals('ACTIVE', $cert->status);
        $this->assertEquals('k3_loto', $cert->skill_competency_code);
        $this->assertStringContainsString('CERT-', $cert->certificate_number);
        $this->assertEquals(64, strlen($cert->certificate_hash));
    }

    public function test_failed_exam_attempt_blocks_certificate_issuance(): void
    {
        $program = $this->createProgram('PRG-SAFE', 'Industrial Safety');
        $cohort = $this->createCohort($program);
        $enrollment = $this->createEnrollment($cohort, 'student-safe-fail');
        $attempt = $this->createExamAttempt($enrollment, 'student-safe-fail', score: 50, passed: false);

        $action = app(IssueVerifiedCompetencyCertificateAction::class);

        $this->expectException(ExamNotPassedException::class);
        $action->execute([
            'enrollment_id' => $enrollment->id,
            'exam_attempt_id' => $attempt->id,
            'skill_competency_code' => 'k3_loto',
            'idempotency_key' => 'edu:cert:issue:fail:01',
        ]);
    }

    public function test_sequential_gapless_numbering_per_program(): void
    {
        $program = $this->createProgram('PRG-SEQ', 'Gapless Sequence Test Track');
        $cohort = $this->createCohort($program);
        $action = app(IssueVerifiedCompetencyCertificateAction::class);

        $year = now()->year;

        $certs = [];
        for ($i = 1; $i <= 3; $i++) {
            $enrollment = $this->createEnrollment($cohort, "student-seq-{$i}");
            $attempt = $this->createExamAttempt($enrollment, "student-seq-{$i}", score: 85, passed: true);

            $cert = $action->execute([
                'enrollment_id' => $enrollment->id,
                'exam_attempt_id' => $attempt->id,
                'skill_competency_code' => 'k3_loto',
                'idempotency_key' => "edu:cert:issue:seq:0{$i}",
            ]);

            $certs[] = $cert;
            $expectedNumber = sprintf('CERT-%d-%s-%06d', $year, $program->program_code, $i);
            $this->assertEquals($expectedNumber, $cert->certificate_number);
        }
    }

    public function test_canonical_hash_chain_generated_per_program(): void
    {
        $program = $this->createProgram('PRG-CHAIN', 'Hash Chain Program');
        $cohort = $this->createCohort($program);
        $action = app(IssueVerifiedCompetencyCertificateAction::class);

        $cert1 = $action->execute([
            'enrollment_id' => ($enr1 = $this->createEnrollment($cohort, 'student-c1'))->id,
            'exam_attempt_id' => $this->createExamAttempt($enr1, 'student-c1', 80, true)->id,
            'skill_competency_code' => 'prog_chain',
            'idempotency_key' => 'edu:cert:chain:01',
        ]);

        // First certificate in program uses genesis previous hash
        $this->assertEquals(CanonicalHashChain::GENESIS_PREV_HASH, $cert1->prev_hash);

        $cert2 = $action->execute([
            'enrollment_id' => ($enr2 = $this->createEnrollment($cohort, 'student-c2'))->id,
            'exam_attempt_id' => $this->createExamAttempt($enr2, 'student-c2', 88, true)->id,
            'skill_competency_code' => 'prog_chain',
            'idempotency_key' => 'edu:cert:chain:02',
        ]);

        // Second certificate links directly to first certificate's hash
        $this->assertEquals($cert1->certificate_hash, $cert2->prev_hash);
    }

    public function test_certificate_model_guard_rejects_delete(): void
    {
        $program = $this->createProgram('PRG-IMMUT', 'Immutability Check');
        $cohort = $this->createCohort($program);
        $enrollment = $this->createEnrollment($cohort, 'student-immut');
        $attempt = $this->createExamAttempt($enrollment, 'student-immut', 85, true);

        $action = app(IssueVerifiedCompetencyCertificateAction::class);
        $cert = $action->execute([
            'enrollment_id' => $enrollment->id,
            'exam_attempt_id' => $attempt->id,
            'skill_competency_code' => 'k3_loto',
            'idempotency_key' => 'edu:cert:immut:01',
        ]);

        $this->expectException(DomainException::class);
        $cert->delete();
    }

    public function test_duplicate_certificate_issuance_returns_existing(): void
    {
        $program = $this->createProgram('PRG-DUP', 'Duplicate Test');
        $cohort = $this->createCohort($program);
        $enrollment = $this->createEnrollment($cohort, 'student-dup');
        $attempt = $this->createExamAttempt($enrollment, 'student-dup', 85, true);

        $action = app(IssueVerifiedCompetencyCertificateAction::class);
        $key = 'edu:cert:dup:01';

        $c1 = $action->execute([
            'enrollment_id' => $enrollment->id,
            'exam_attempt_id' => $attempt->id,
            'skill_competency_code' => 'k3_loto',
            'idempotency_key' => $key,
        ]);

        $c2 = $action->execute([
            'enrollment_id' => $enrollment->id,
            'exam_attempt_id' => $attempt->id,
            'skill_competency_code' => 'k3_loto',
            'idempotency_key' => $key,
        ]);

        $this->assertEquals($c1->id, $c2->id);
    }

    protected function createProgram(string $code, string $title): EduProgram
    {
        return EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => $code,
            'title' => $title,
            'industry_sector' => 'MANUFACTURING',
            'tuition_fee_minor' => 6_000_000,
            'passing_score' => 75,
            'validity_months' => 24,
            'status' => 'ACTIVE',
        ]);
    }

    protected function createCohort(EduProgram $program): EduCohort
    {
        return EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-'.strtoupper(Str::random(6)),
            'program_id' => $program->id,
            'instructor_party_id' => 'instructor-01',
            'max_capacity' => 20,
            'enrolled_count' => 0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'ACTIVE',
        ]);
    }

    protected function createEnrollment(EduCohort $cohort, string $studentId): EduEnrollment
    {
        return EduEnrollment::create([
            'id' => (string) Str::uuid(),
            'enrollment_code' => 'ENR-'.strtoupper(Str::random(8)),
            'student_party_id' => $studentId,
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 6_000_000,
            'sessions_attended' => 10,
            'total_sessions' => 10,
            'status' => 'ENROLLED',
        ]);
    }

    protected function createExamAttempt(EduEnrollment $enrollment, string $studentId, int $score, bool $passed): EduExamAttempt
    {
        return EduExamAttempt::create([
            'id' => (string) Str::uuid(),
            'attempt_code' => 'EXAM-'.strtoupper(Str::random(8)),
            'student_party_uuid' => $studentId,
            'enrollment_id' => $enrollment->id,
            'exam_title' => 'Final Certification Exam',
            'score' => $score,
            'passing_score' => 75,
            'is_passed' => $passed,
            'proctor_verification_hash' => hash('sha256', "proctor:{$studentId}"),
            'status' => 'COMPLETED',
        ]);
    }
}
