<?php

declare(strict_types=1);

namespace Modules\Edu\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Edu\Application\Actions\DisburseResearchGrantMilestoneAction;
use Modules\Edu\Application\Actions\EnrollStudentInCohortAction;
use Modules\Edu\Application\Actions\EvaluateProctoredExamAttemptAction;
use Modules\Edu\Application\Actions\IssueVerifiedCompetencyCertificateAction;
use Modules\Edu\Application\Actions\ReconcileEduScholarshipEscrowAction;
use Modules\Edu\Application\Actions\SettleCorporateAcademyBillingAction;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduGrantMilestone;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Edu\Domain\Models\EduResearchGrant;
use Modules\Edu\Domain\Models\EduScholarshipEscrow;
use RuntimeException;
use Tests\TestCase;

class EduVerticalSliceHttpAndDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    private function createSampleProgramAndCohort(int $capacity = 30): array
    {
        $program = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-TEST-'.Str::random(5),
            'title' => 'Advanced Automotive Engineering',
            'industry_sector' => 'AUTOMOTIVE',
            'total_sessions' => 10,
            'tuition_fee_minor' => 15000000,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'CHT-TEST-'.Str::random(5),
            'program_id' => $program->id,
            'instructor_party_id' => 'PTY-INST-001',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'max_capacity' => $capacity,
            'enrolled_count' => 0,
            'status' => 'OPEN',
        ]);

        return [$program, $cohort];
    }

    public function test_1_authorized_user_can_access_edu_academy_index(): void
    {
        [$program, $cohort] = $this->createSampleProgramAndCohort();
        $user = User::factory()->create(['role' => 'edu_admin']);

        $response = $this->actingAs($user)->get(route('edu.academy.index'));
        $response->assertOk();
        $response->assertSeeText('Academy & LMS Cohort Operations');
        $response->assertSeeText($cohort->cohort_code);
    }

    public function test_2_authorized_user_can_enroll_student_in_cohort_via_http(): void
    {
        [$program, $cohort] = $this->createSampleProgramAndCohort();
        $user = User::factory()->create(['role' => 'instructor']);

        $response = $this->actingAs($user)->post(route('edu.academy.enroll'), [
            'student_party_id' => 'PTY-STUDENT-888',
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 15000000,
        ]);

        $response->assertRedirect(route('edu.academy.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('edu_enrollments', [
            'student_party_id' => 'PTY-STUDENT-888',
            'cohort_id' => $cohort->id,
            'status' => 'ENROLLED',
        ]);
        $this->assertSame(1, $cohort->fresh()->enrolled_count);
    }

    public function test_3_enrollment_rejected_when_cohort_is_full(): void
    {
        [$program, $cohort] = $this->createSampleProgramAndCohort(capacity: 1);
        $action = app(EnrollStudentInCohortAction::class);

        // First enrolls
        $action->execute([
            'student_party_id' => 'STUDENT-1',
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 15000000,
        ]);

        // Second should fail
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has reached maximum capacity');

        $action->execute([
            'student_party_id' => 'STUDENT-2',
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 15000000,
        ]);
    }

    public function test_4_issue_and_verify_certificate_with_sha256_hash(): void
    {
        [$program, $cohort] = $this->createSampleProgramAndCohort();
        $enrollment = app(EnrollStudentInCohortAction::class)->execute([
            'student_party_id' => 'STUDENT-CERT',
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 15000000,
        ]);

        $action = app(IssueVerifiedCompetencyCertificateAction::class);
        $cert = $action->execute([
            'enrollment_id' => $enrollment->id,
            'skill_competency_code' => 'AUTOSERVE_EV_TECH',
            'expires_at' => now()->addYears(2)->toDateString(),
        ]);

        $this->assertNotEmpty($cert->certificate_hash);
        $this->assertSame('GRADUATED', $enrollment->fresh()->status);

        $user = User::factory()->create(['role' => 'cert_officer']);
        $verifyRes = $this->actingAs($user)->get(route('edu.certification.verify', $cert->certificate_hash));
        $verifyRes->assertOk();
        $verifyRes->assertSeeText('SERTIFIKAT RESMI VALID & TERDAFTAR');
    }

    public function test_5_disburse_research_grant_milestone_and_ledger(): void
    {
        $grant = EduResearchGrant::create([
            'id' => (string) Str::uuid(),
            'grant_code' => 'GRNT-'.Str::random(6),
            'title' => 'Hydrogen Fuel Cell Microgrid Efficiency',
            'principal_investigator_name' => 'Prof. Dr. Aris',
            'institution_party_uuid' => 'PTY-INST-UNIV',
            'total_grant_minor' => 100000000,
            'disbursed_grant_minor' => 0,
            'status' => 'ACTIVE',
        ]);

        $milestone = EduGrantMilestone::create([
            'id' => (string) Str::uuid(),
            'milestone_code' => 'MLS-'.Str::random(6),
            'grant_uuid' => $grant->id,
            'milestone_title' => 'Prototype Delivery Phase 1',
            'disbursement_amount_minor' => 35000000,
            'status' => 'PENDING',
        ]);

        $action = app(DisburseResearchGrantMilestoneAction::class);
        $disbursed = $action->execute($milestone->id);

        $this->assertSame('DISBURSED', $disbursed->status);
        $this->assertSame(35000000, $grant->fresh()->disbursed_grant_minor);

        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'EDU_RESEARCH_GRANT_DISBURSE',
            'reference_id' => $milestone->milestone_code,
        ]);
    }

    public function test_6_settle_corporate_academy_billing(): void
    {
        [$program, $cohort] = $this->createSampleProgramAndCohort();
        $corpClient = 'CORP-CLIENT-ASTRA';

        app(EnrollStudentInCohortAction::class)->execute([
            'student_party_id' => 'STU-CORP-1',
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 10000000,
            'corporate_client_id' => $corpClient,
        ]);

        app(EnrollStudentInCohortAction::class)->execute([
            'student_party_id' => 'STU-CORP-2',
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 10000000,
            'corporate_client_id' => $corpClient,
        ]);

        $settleAction = app(SettleCorporateAcademyBillingAction::class);
        $settledTotal = $settleAction->execute($cohort->id, $corpClient);

        $this->assertSame(20000000, $settledTotal);
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'EDU_CORPORATE_SETTLEMENT',
        ]);
    }

    public function test_7_evaluate_proctored_exam_attempt_and_reconcile_escrow(): void
    {
        $examAction = app(EvaluateProctoredExamAttemptAction::class);
        $attempt = $examAction->execute([
            'student_party_uuid' => 'STU-EXAM-77',
            'enrollment_code' => 'ENR-EXAM-77',
            'exam_title' => 'High Voltage Battery Safety Proctored Exam',
            'score' => 88,
            'passing_score' => 75,
            'proctor_signature' => 'SIG-KEY-999',
        ]);

        $this->assertTrue($attempt->is_passed);
        $this->assertSame('COMPLETED', $attempt->status);
        $this->assertNotEmpty($attempt->proctor_verification_hash);

        // Reconcile escrow
        $escrow = EduScholarshipEscrow::create([
            'id' => (string) Str::uuid(),
            'escrow_code' => 'ESCROW-Djarum-2026',
            'sponsor_name' => 'Djarum Foundation',
            'total_escrow_minor' => 50000000,
            'allocated_minor' => 0,
            'disbursed_minor' => 0,
            'status' => 'ACTIVE',
        ]);

        $escrowAction = app(ReconcileEduScholarshipEscrowAction::class);
        $reconciled = $escrowAction->execute($escrow->id, 50000000);

        $this->assertSame('RECONCILED', $reconciled->status);
        $this->assertSame(50000000, $reconciled->disbursed_minor);
    }

    public function test_8_edu_audit_command_and_security_rbac(): void
    {
        $this->createSampleProgramAndCohort();

        $exitCode = Artisan::call('edu:audit');
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Edu audit PASSED with 0 discrepancy', Artisan::output());

        // Unauthorized user 403
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get(route('edu.academy.index'))->assertForbidden();

        // Unauthenticated user 302
        auth()->logout();
        $this->get(route('edu.academy.index'))->assertRedirect(route('login'));
    }
}
