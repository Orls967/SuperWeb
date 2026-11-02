<?php

declare(strict_types=1);

namespace Tests\Acceptance\Academy\Eksplorasi;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Edu\Application\Actions\DisburseResearchGrantMilestoneAction;
use Modules\Edu\Application\Actions\EnrollStudentInCohortAction;
use Modules\Edu\Application\Actions\EvaluateProctoredExamAttemptAction;
use Modules\Edu\Application\Actions\IssueVerifiedCompetencyCertificateAction;
use Modules\Edu\Application\Actions\ReconcileEduScholarshipEscrowAction;
use Modules\Edu\Application\Actions\SettleCorporateAcademyBillingAction;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduGrantMilestone;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Edu\Domain\Models\EduResearchGrant;
use Modules\Edu\Domain\Models\EduScholarshipEscrow;
use Tests\TestCase;

/**
 * L16.0: Buktikan kondisi kode lama Lini 16 (Pendidikan & Talent).
 *
 * Klasifikasi Action Lama:
 * 1. EnrollStudentInCohortAction: SEBAGIAN (ada transaksi & ledger, tapi prasyarat hanya direct 1 tingkat tanpa graf DAG transitif, kuota korporat belum diverifikasi, microtime di nomor enrollment).
 * 2. EvaluateProctoredExamAttemptAction: BENAR (mencatat attempt proctored exam append-only dan proctor verification hash).
 * 3. IssueVerifiedCompetencyCertificateAction: SALAH (penomoran bukan gapless, hash bukan hash-chain kanonik per program, QR bukan {uuid}.{key_id}.{hmac}, tidak memvalidasi attempt kelulusan).
 * 4. SettleCorporateAcademyBillingAction: SEBAGIAN (ada ledger settlement, tapi belum mendukung Intercompany billing untuk klien entitas grup).
 * 5. ReconcileEduScholarshipEscrowAction: BENAR (rekonsiliasi pencairan beasiswa dengan limit pool dan ledger posting debit liability -> credit revenue).
 * 6. DisburseResearchGrantMilestoneAction: BENAR (pencairan milestone riset dengan audit trail).
 */
class AcademyExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_explores_enroll_student_in_cohort_action(): void
    {
        $program = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-EXP-01',
            'title' => 'Exploration Academy Track',
            'industry_sector' => 'TECHNOLOGY',
            'total_sessions' => 12,
            'tuition_fee_minor' => 5_000_000,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-EXP-01',
            'program_id' => $program->id,
            'instructor_party_id' => (string) Str::uuid(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'max_capacity' => 20,
            'enrolled_count' => 0,
            'status' => 'ACTIVE',
        ]);

        $action = app(EnrollStudentInCohortAction::class);
        $enrollment = $action->execute([
            'student_party_id' => 'student-party-01',
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 5_000_000,
        ]);

        $this->assertInstanceOf(EduEnrollment::class, $enrollment);
        $this->assertEquals('ENROLLED', $enrollment->status);
        $this->assertEquals(1, $cohort->fresh()->enrolled_count);
    }

    public function test_explores_evaluate_proctored_exam_attempt_action(): void
    {
        $action = app(EvaluateProctoredExamAttemptAction::class);
        $attempt = $action->execute([
            'student_party_uuid' => 'student-party-02',
            'enrollment_code' => 'ENR-EXP-02',
            'exam_title' => 'Fullstack Core Certification Exam',
            'score' => 88,
            'passing_score' => 75,
            'proctor_signature' => 'AI_PROCTOR_V2_SEAL',
        ]);

        $this->assertTrue($attempt->is_passed);
        $this->assertEquals(88, $attempt->score);
        $this->assertNotEmpty($attempt->proctor_verification_hash);
    }

    public function test_explores_issue_verified_competency_certificate_action(): void
    {
        $program = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-EXP-03',
            'title' => 'Welding & NDT Inspection Track',
            'industry_sector' => 'MANUFACTURING',
            'total_sessions' => 8,
            'tuition_fee_minor' => 8_000_000,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-EXP-03',
            'program_id' => $program->id,
            'instructor_party_id' => (string) Str::uuid(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'max_capacity' => 15,
            'enrolled_count' => 1,
            'status' => 'ACTIVE',
        ]);

        $enrollment = EduEnrollment::create([
            'id' => (string) Str::uuid(),
            'enrollment_code' => 'ENR-EXP-03',
            'student_party_id' => 'student-party-03',
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 8_000_000,
            'sessions_attended' => 8,
            'total_sessions' => 8,
            'refund_amount_minor' => 0,
            'status' => 'ENROLLED',
        ]);

        $action = app(IssueVerifiedCompetencyCertificateAction::class);
        $cert = $action->execute([
            'enrollment_id' => $enrollment->id,
            'skill_competency_code' => 'k3_loto',
        ]);

        $this->assertEquals('ACTIVE', $cert->status);
        $this->assertEquals('k3_loto', $cert->skill_competency_code);
    }

    public function test_explores_settle_corporate_academy_billing_action(): void
    {
        $program = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-EXP-04',
            'title' => 'Enterprise Security Track',
            'industry_sector' => 'TELECOM',
            'total_sessions' => 6,
            'tuition_fee_minor' => 10_000_000,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-CORP-EXP-04',
            'program_id' => $program->id,
            'instructor_party_id' => (string) Str::uuid(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(1)->toDateString(),
            'max_capacity' => 30,
            'enrolled_count' => 1,
            'status' => 'ACTIVE',
        ]);

        EduEnrollment::create([
            'id' => (string) Str::uuid(),
            'enrollment_code' => 'ENR-CORP-01',
            'student_party_id' => 'emp-party-01',
            'cohort_id' => $cohort->id,
            'corporate_client_id' => 'CORP-CLIENT-EXP-01',
            'amount_paid_minor' => 10_000_000,
            'sessions_attended' => 0,
            'total_sessions' => 6,
            'refund_amount_minor' => 0,
            'status' => 'ENROLLED',
        ]);

        $action = app(SettleCorporateAcademyBillingAction::class);
        $totalSettled = $action->execute($cohort->id, 'CORP-CLIENT-EXP-01');

        $this->assertEquals(10_000_000, $totalSettled);
    }

    public function test_explores_reconcile_edu_scholarship_escrow_action(): void
    {
        $escrow = EduScholarshipEscrow::create([
            'id' => (string) Str::uuid(),
            'escrow_code' => 'ESC-EXP-01',
            'sponsor_name' => 'CSR Foundation',
            'total_escrow_minor' => 50_000_000,
            'allocated_minor' => 0,
            'disbursed_minor' => 0,
            'status' => 'OPEN',
        ]);

        $action = app(ReconcileEduScholarshipEscrowAction::class);
        $reconciled = $action->execute($escrow->id, 15_000_000);

        $this->assertEquals(15_000_000, $reconciled->disbursed_minor);
        $this->assertEquals('OPEN', $reconciled->status);
    }

    public function test_explores_disburse_research_grant_milestone_action(): void
    {
        $grant = EduResearchGrant::create([
            'id' => (string) Str::uuid(),
            'grant_code' => 'GRANT-EXP-01',
            'title' => 'Quantum AI Research',
            'institution_party_uuid' => (string) Str::uuid(),
            'principal_investigator_name' => 'Dr. Alice Quantum',
            'total_grant_minor' => 100_000_000,
            'disbursed_grant_minor' => 0,
            'status' => 'ACTIVE',
        ]);

        $milestone = EduGrantMilestone::create([
            'id' => (string) Str::uuid(),
            'milestone_code' => 'MILE-EXP-01',
            'grant_uuid' => $grant->id,
            'milestone_title' => 'Initial Proof of Concept',
            'disbursement_amount_minor' => 25_000_000,
            'status' => 'PENDING',
        ]);

        $action = app(DisburseResearchGrantMilestoneAction::class);
        $disbursed = $action->execute($milestone->id);

        $this->assertEquals('DISBURSED', $disbursed->status);
        $this->assertEquals(25_000_000, $grant->fresh()->disbursed_grant_minor);
    }
}
