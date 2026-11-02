<?php

declare(strict_types=1);

namespace Tests\Acceptance\Academy;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Edu\Application\Actions\AddProgramPrerequisiteAction;
use Modules\Edu\Application\Actions\EnrollStudentInCohortAction;
use Modules\Edu\Domain\Exceptions\PrerequisiteCycleException;
use Modules\Edu\Domain\Exceptions\PrerequisiteNotMetException;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduPrerequisite;
use Modules\Edu\Domain\Models\EduProgram;
use Tests\TestCase;

class CurriculumPrerequisiteAndDagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_program_creation_with_valid_attributes(): void
    {
        $program = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-CYB-101',
            'title' => 'Cybersecurity Foundations',
            'industry_sector' => 'TELECOM',
            'tuition_fee_minor' => 5_000_000,
            'passing_score' => 75,
            'validity_months' => 24,
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('edu_programs', ['program_code' => 'PRG-CYB-101']);
        $this->assertEquals(75, $program->passing_score);
    }

    public function test_direct_prerequisite_addition_succeeds(): void
    {
        $progA = $this->createProgram('PRG-A', 'Basics');
        $progB = $this->createProgram('PRG-B', 'Intermediate');

        $action = app(AddProgramPrerequisiteAction::class);
        $prereq = $action->execute([
            'program_id' => $progB->id,
            'prerequisite_program_id' => $progA->id,
        ]);

        $this->assertInstanceOf(EduPrerequisite::class, $prereq);
        $this->assertDatabaseHas('edu_prerequisites', [
            'program_id' => $progB->id,
            'prerequisite_program_id' => $progA->id,
        ]);
    }

    public function test_prerequisite_cycle_detection_rejects_and_returns_cycle_path(): void
    {
        // A -> B -> C, then try C -> A (cycle!)
        $progA = $this->createProgram('PRG-A', 'Course A');
        $progB = $this->createProgram('PRG-B', 'Course B');
        $progC = $this->createProgram('PRG-C', 'Course C');

        $action = app(AddProgramPrerequisiteAction::class);
        $action->execute(['program_id' => $progB->id, 'prerequisite_program_id' => $progA->id]);
        $action->execute(['program_id' => $progC->id, 'prerequisite_program_id' => $progB->id]);

        $this->expectException(PrerequisiteCycleException::class);
        // Attempting to add A requiring C creates cycle: A -> B -> C -> A
        $action->execute(['program_id' => $progA->id, 'prerequisite_program_id' => $progC->id]);
    }

    public function test_self_referencing_prerequisite_rejected(): void
    {
        $progA = $this->createProgram('PRG-SELF', 'Self Dependent Course');

        $action = app(AddProgramPrerequisiteAction::class);
        $this->expectException(PrerequisiteCycleException::class);
        $action->execute(['program_id' => $progA->id, 'prerequisite_program_id' => $progA->id]);
    }

    public function test_multi_level_transitive_prerequisite_enforcement(): void
    {
        // Level 1: Core Math -> Level 2: Data Science -> Level 3: Advanced AI
        $p1 = $this->createProgram('P1-MATH', 'Core Math');
        $p2 = $this->createProgram('P2-DS', 'Data Science');
        $p3 = $this->createProgram('P3-AI', 'Advanced AI');

        $action = app(AddProgramPrerequisiteAction::class);
        $action->execute(['program_id' => $p2->id, 'prerequisite_program_id' => $p1->id]);
        $action->execute(['program_id' => $p3->id, 'prerequisite_program_id' => $p2->id]);

        $cohort3 = $this->createCohort($p3);

        $enrollAction = app(EnrollStudentInCohortAction::class);

        // Student has no certificates -> rejected on transitive prerequisite
        $this->expectException(PrerequisiteNotMetException::class);
        $enrollAction->execute([
            'student_party_id' => 'student-math-none',
            'cohort_id' => $cohort3->id,
            'idempotency_key' => 'edu:enr:prereq:fail:01',
        ]);
    }

    public function test_enrollment_fails_if_only_intermediate_prerequisite_is_completed(): void
    {
        $p1 = $this->createProgram('P1-CORE', 'Level 1 Core');
        $p2 = $this->createProgram('P2-MID', 'Level 2 Intermediate');
        $p3 = $this->createProgram('P3-ADV', 'Level 3 Advanced');

        $action = app(AddProgramPrerequisiteAction::class);
        $action->execute(['program_id' => $p2->id, 'prerequisite_program_id' => $p1->id]);
        $action->execute(['program_id' => $p3->id, 'prerequisite_program_id' => $p2->id]);

        $cohort3 = $this->createCohort($p3);

        // Student only has cert for P2, but missing P1
        $studentId = 'student-has-p2-only';
        $this->createActiveCertificate($studentId, $p2);

        $enrollAction = app(EnrollStudentInCohortAction::class);
        $this->expectException(PrerequisiteNotMetException::class);
        $enrollAction->execute([
            'student_party_id' => $studentId,
            'cohort_id' => $cohort3->id,
            'idempotency_key' => 'edu:enr:prereq:partial:01',
        ]);
    }

    public function test_enrollment_succeeds_when_all_transitive_prerequisites_are_active(): void
    {
        $p1 = $this->createProgram('P1-A', 'Level 1');
        $p2 = $this->createProgram('P2-B', 'Level 2');
        $p3 = $this->createProgram('P3-C', 'Level 3');

        $action = app(AddProgramPrerequisiteAction::class);
        $action->execute(['program_id' => $p2->id, 'prerequisite_program_id' => $p1->id]);
        $action->execute(['program_id' => $p3->id, 'prerequisite_program_id' => $p2->id]);

        $cohort3 = $this->createCohort($p3);

        $studentId = 'student-all-stars';
        $this->createActiveCertificate($studentId, $p1);
        $this->createActiveCertificate($studentId, $p2);

        $enrollAction = app(EnrollStudentInCohortAction::class);
        $enrollment = $enrollAction->execute([
            'student_party_id' => $studentId,
            'cohort_id' => $cohort3->id,
            'idempotency_key' => 'edu:enr:prereq:pass:01',
        ]);

        $this->assertEquals('ENROLLED', $enrollment->status);
    }

    public function test_duplicate_prerequisite_addition_is_idempotent(): void
    {
        $progA = $this->createProgram('PRG-DUP-A', 'Course A');
        $progB = $this->createProgram('PRG-DUP-B', 'Course B');

        $action = app(AddProgramPrerequisiteAction::class);
        $p1 = $action->execute(['program_id' => $progB->id, 'prerequisite_program_id' => $progA->id]);
        $p2 = $action->execute(['program_id' => $progB->id, 'prerequisite_program_id' => $progA->id]);

        $this->assertEquals($p1->id, $p2->id);
    }

    public function test_action_accepts_model_instances_and_string_prerequisite_id(): void
    {
        $progA = $this->createProgram('PRG-MODEL-A', 'Course Model A');
        $progB = $this->createProgram('PRG-MODEL-B', 'Course Model B');
        $progC = $this->createProgram('PRG-MODEL-C', 'Course Model C');

        $action = app(AddProgramPrerequisiteAction::class);

        // 1. Model + Model
        $p1 = $action->execute($progB, $progA);
        $this->assertEquals($progB->id, $p1->program_id);
        $this->assertEquals($progA->id, $p1->prerequisite_program_id);

        // 2. Model + String ID
        $p2 = $action->execute($progC, (string) $progB->id);
        $this->assertEquals($progC->id, $p2->program_id);
        $this->assertEquals($progB->id, $p2->prerequisite_program_id);
    }

    public function test_action_rejects_invalid_arguments(): void
    {
        $progA = $this->createProgram('PRG-INV-A', 'Course Inv A');
        $action = app(AddProgramPrerequisiteAction::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid arguments');
        $action->execute($progA, null);
    }

    public function test_cycle_exception_message_contains_exact_prefix_and_path(): void
    {
        $progA = $this->createProgram('PRG-MSG-A', 'Course Msg A');
        $progB = $this->createProgram('PRG-MSG-B', 'Course Msg B');

        $action = app(AddProgramPrerequisiteAction::class);
        $action->execute(['program_id' => $progB->id, 'prerequisite_program_id' => $progA->id]);

        try {
            $action->execute(['program_id' => $progA->id, 'prerequisite_program_id' => $progB->id]);
            $this->fail('Expected PrerequisiteCycleException was not thrown.');
        } catch (PrerequisiteCycleException $e) {
            $this->assertStringStartsWith('Prerequisite cycle detected in curriculum graph: ', $e->getMessage());
            $this->assertStringContainsString(' -> ', $e->getMessage());
        }
    }

    public function test_get_transitive_prerequisites_returns_empty_when_program_has_no_prerequisites(): void
    {
        $standaloneProg = $this->createProgram('PRG-ISOLATED', 'Isolated Course');
        $action = app(AddProgramPrerequisiteAction::class);

        $transitive = $action->getTransitivePrerequisites($standaloneProg->id);
        $this->assertSame([], $transitive);
    }

    protected function createProgram(string $code, string $title): EduProgram
    {
        return EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => $code,
            'title' => $title,
            'industry_sector' => 'TECHNOLOGY',
            'tuition_fee_minor' => 4_000_000,
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
            'max_capacity' => 25,
            'enrolled_count' => 0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'ACTIVE',
        ]);
    }

    protected function createActiveCertificate(string $studentId, EduProgram $program): EduCertificate
    {
        return EduCertificate::create([
            'id' => (string) Str::uuid(),
            'certificate_number' => 'CERT-'.strtoupper(Str::random(8)),
            'program_id' => $program->id,
            'student_party_id' => $studentId,
            'skill_competency_code' => strtolower($program->program_code),
            'certificate_hash' => hash('sha256', "cert:{$studentId}:{$program->id}"),
            'qr_verification_url' => 'https://verify.ecosystem.id/cert/mock',
            'issued_at' => now()->toDateString(),
            'expires_at' => now()->addMonths(24)->toDateString(),
            'status' => 'ACTIVE',
        ]);
    }
}
