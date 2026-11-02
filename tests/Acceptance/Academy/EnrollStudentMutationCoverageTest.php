<?php

declare(strict_types=1);

namespace Tests\Acceptance\Academy;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Edu\Application\Actions\AddProgramPrerequisiteAction;
use Modules\Edu\Application\Actions\EnrollStudentInCohortAction;
use Modules\Edu\Domain\Exceptions\CorporateQuotaExceededException;
use Modules\Edu\Domain\Exceptions\IdempotencyConflictException;
use Modules\Edu\Domain\Exceptions\PrerequisiteNotMetException;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduCorporateQuota;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Edu\Domain\Services\CertificateQrSigner;
use Tests\TestCase;

/**
 * Mutation-coverage-focused tests for EnrollStudentInCohortAction.
 *
 * Each test targets specific untested mutations from the 34.23% baseline run:
 *  - Lines 140-152: individual tuition ledger posting (EDU_TUITION_PAYMENT)
 *  - Lines 130-132: corporate quota_used increment
 *  - Lines 44-46:  student-unknown default when both ids are null
 *  - Lines 40-41:  numeric student_party_id auto-cast to student_user_id
 *  - Lines 118-119: enrollment_code determinism & format
 *  - Lines 52-59:  idempotency conflict on differing student (same cohort)
 *  - Lines 78-93:  foreach prerequisite loop fires and blocks enrollment
 *  - Lines 99:     tuition resolved from program when no amount_paid_minor given
 *  - Lines 108:    corporate quota exhausted at quota_used == quota_total
 *  - Line 140:     tuition=0 skips ledger posting
 *  - Lines 155-160: audit call with cohort_code and amount_paid
 */
class EnrollStudentMutationCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    // ──────────────────────────────────────────────
    // 1. Individual tuition ledger posting (Lines 140-152)
    // ──────────────────────────────────────────────

    public function test_individual_enrollment_posts_tuition_receivable_and_revenue_entries(): void
    {
        $program = $this->makeProgram(tuition: 3_500_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        $action = app(EnrollStudentInCohortAction::class);

        $enrollment = $action->execute([
            'student_party_id' => 'student-ledger-01',
            'cohort_id' => $cohort->id,
            'idempotency_key' => 'edu:enr:ledger:01',
        ]);

        // Exactly one transaction of type EDU_TUITION_PAYMENT must exist
        $txCount = DB::table('bank_ledger_transactions')
            ->where('type', 'EDU_TUITION_PAYMENT')
            ->count();
        $this->assertEquals(1, $txCount, 'Expected exactly one EDU_TUITION_PAYMENT transaction.');

        // Debit side: edu:tuition_receivable:IDR
        // Single-sign convention (KONSEP §A2.1): debit = negative in bank_ledger_entries
        $debit = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'edu:tuition_receivable:IDR'))
            ->whereHas('transaction', fn ($q) => $q->where('type', 'EDU_TUITION_PAYMENT'))
            ->first();
        $this->assertNotNull($debit, 'Debit entry edu:tuition_receivable:IDR must exist.');
        // Debit is stored as negative per single-sign convention
        $this->assertStringStartsWith('-', (string) $debit->amount, 'Debit entry must have negative sign.');
        $this->assertEquals(-3_500_000, (int) round((float) $debit->amount),
            'Debit magnitude must equal program tuition -3500000.');

        // Credit side: edu:tuition_revenue:IDR (positive)
        $credit = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'edu:tuition_revenue:IDR'))
            ->whereHas('transaction', fn ($q) => $q->where('type', 'EDU_TUITION_PAYMENT'))
            ->first();
        $this->assertNotNull($credit, 'Credit entry edu:tuition_revenue:IDR must exist.');
        $this->assertGreaterThan(0, (float) $credit->amount, 'Credit entry must be positive.');
        $this->assertEquals(3_500_000, (int) round((float) $credit->amount),
            'Credit magnitude must equal program tuition 3500000.');

        // Idempotency key format: 'edu:pay:{enrollment_code}'
        $tx = DB::table('bank_ledger_transactions')
            ->where('type', 'EDU_TUITION_PAYMENT')
            ->first();
        $this->assertStringStartsWith('edu:pay:', $tx->idempotency_key);
        $this->assertStringContainsString($enrollment->enrollment_code, $tx->idempotency_key);
    }

    public function test_corporate_enrollment_does_not_post_individual_tuition_ledger(): void
    {
        $program = $this->makeProgram(tuition: 2_000_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        $quota = $this->makeQuota($program, corporateClientId: 'corp-ledger-skip', total: 5, unitPrice: 1_800_000);

        $action = app(EnrollStudentInCohortAction::class);
        $action->execute([
            'student_party_id' => 'corp-emp-01',
            'cohort_id' => $cohort->id,
            'corporate_client_id' => 'corp-ledger-skip',
            'idempotency_key' => 'edu:enr:corp:no-ledger:01',
        ]);

        $txCount = DB::table('bank_ledger_transactions')
            ->where('type', 'EDU_TUITION_PAYMENT')
            ->count();
        $this->assertEquals(0, $txCount, 'Corporate enrollment must NOT produce EDU_TUITION_PAYMENT.');
    }

    public function test_zero_tuition_enrollment_does_not_post_ledger(): void
    {
        $program = $this->makeProgram(tuition: 0);
        $cohort = $this->makeCohort($program, capacity: 10);
        $action = app(EnrollStudentInCohortAction::class);

        $action->execute([
            'student_party_id' => 'student-free-01',
            'cohort_id' => $cohort->id,
            'idempotency_key' => 'edu:enr:free:01',
        ]);

        $txCount = DB::table('bank_ledger_transactions')
            ->where('type', 'EDU_TUITION_PAYMENT')
            ->count();
        $this->assertEquals(0, $txCount, 'Zero-tuition enrollment must not post ledger.');
    }

    // ──────────────────────────────────────────────
    // 2. Corporate quota_used increment (Lines 112-113)
    // ──────────────────────────────────────────────

    public function test_corporate_enrollment_increments_quota_used_by_one(): void
    {
        $program = $this->makeProgram(tuition: 2_000_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        $quota = $this->makeQuota($program, corporateClientId: 'corp-quota-incr', total: 5, unitPrice: 1_500_000);

        $this->assertEquals(0, $quota->quota_used);

        $action = app(EnrollStudentInCohortAction::class);
        $action->execute([
            'student_party_id' => 'corp-emp-incr-01',
            'cohort_id' => $cohort->id,
            'corporate_client_id' => 'corp-quota-incr',
            'idempotency_key' => 'edu:enr:quota-incr:01',
        ]);

        $this->assertEquals(1, $quota->fresh()->quota_used);

        $action->execute([
            'student_party_id' => 'corp-emp-incr-02',
            'cohort_id' => $cohort->id,
            'corporate_client_id' => 'corp-quota-incr',
            'idempotency_key' => 'edu:enr:quota-incr:02',
        ]);

        $this->assertEquals(2, $quota->fresh()->quota_used);
    }

    public function test_corporate_quota_exhausted_at_boundary_quota_used_equals_quota_total(): void
    {
        $program = $this->makeProgram(tuition: 1_000_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        // total=1, used=0 → allow first, reject second
        $quota = $this->makeQuota($program, corporateClientId: 'corp-boundary', total: 1, unitPrice: 1_000_000);

        $action = app(EnrollStudentInCohortAction::class);
        $action->execute([
            'student_party_id' => 'corp-boundary-emp-01',
            'cohort_id' => $cohort->id,
            'corporate_client_id' => 'corp-boundary',
            'idempotency_key' => 'edu:enr:boundary:01',
        ]);
        $this->assertEquals(1, $quota->fresh()->quota_used);

        $this->expectException(CorporateQuotaExceededException::class);
        $action->execute([
            'student_party_id' => 'corp-boundary-emp-02',
            'cohort_id' => $cohort->id,
            'corporate_client_id' => 'corp-boundary',
            'idempotency_key' => 'edu:enr:boundary:02',
        ]);
    }

    // ──────────────────────────────────────────────
    // 3. student-unknown default (Lines 44-46)
    // ──────────────────────────────────────────────

    public function test_enrollment_without_student_id_or_party_defaults_to_student_unknown(): void
    {
        $program = $this->makeProgram(tuition: 500_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        $action = app(EnrollStudentInCohortAction::class);

        $enrollment = $action->execute([
            'cohort_id' => $cohort->id,
            'idempotency_key' => 'edu:enr:unknown:01',
            // no student_user_id, no student_party_id
        ]);

        $this->assertEquals('student-unknown', $enrollment->student_party_id);
    }

    public function test_enrollment_with_only_student_user_id_generates_party_id(): void
    {
        $user = User::factory()->create();
        $program = $this->makeProgram(tuition: 500_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        $action = app(EnrollStudentInCohortAction::class);

        $enrollment = $action->execute([
            'student_user_id' => $user->id,
            'cohort_id' => $cohort->id,
            'idempotency_key' => 'edu:enr:user-only:01',
        ]);

        $this->assertEquals("student-{$user->id}", $enrollment->student_party_id);
        $this->assertEquals($user->id, $enrollment->student_user_id);
    }

    // ──────────────────────────────────────────────
    // 4. Numeric student_party_id auto-cast (Lines 40-41)
    // ──────────────────────────────────────────────

    public function test_numeric_student_party_id_sets_student_user_id(): void
    {
        $user = User::factory()->create();
        $program = $this->makeProgram(tuition: 1_000_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        $action = app(EnrollStudentInCohortAction::class);

        $enrollment = $action->execute([
            'student_party_id' => (string) $user->id,  // numeric string
            'cohort_id' => $cohort->id,
            'idempotency_key' => 'edu:enr:numeric-party:01',
        ]);

        $this->assertEquals($user->id, $enrollment->student_user_id,
            'Numeric student_party_id must be cast to student_user_id.');
        $this->assertEquals((string) $user->id, $enrollment->student_party_id);
    }

    // ──────────────────────────────────────────────
    // 5. Enrollment code determinism (Lines 118-119)
    // ──────────────────────────────────────────────

    public function test_enrollment_code_starts_with_enr_prefix(): void
    {
        $program = $this->makeProgram(tuition: 2_000_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        $action = app(EnrollStudentInCohortAction::class);

        $enrollment = $action->execute([
            'student_party_id' => 'student-code-01',
            'cohort_id' => $cohort->id,
            'idempotency_key' => 'edu:enr:code-test:01',
        ]);

        $this->assertStringStartsWith('ENR-', $enrollment->enrollment_code);
        // Length: 'ENR-' + 10 uppercase hex chars = 14
        $this->assertEquals(14, strlen($enrollment->enrollment_code));
    }

    public function test_enrollment_code_is_deterministic_for_same_cohort_student_key(): void
    {
        $program = $this->makeProgram(tuition: 2_000_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        $key = 'edu:enr:code-determ:01';

        // Compute expected code using the same formula as the action
        $codeSeed = $cohort->id.':student-determ-01:'.$key;
        $expected = 'ENR-'.strtoupper(substr(hash('sha256', $codeSeed), 0, 10));

        $action = app(EnrollStudentInCohortAction::class);
        $enrollment = $action->execute([
            'student_party_id' => 'student-determ-01',
            'cohort_id' => $cohort->id,
            'idempotency_key' => $key,
        ]);

        $this->assertEquals($expected, $enrollment->enrollment_code);
    }

    public function test_custom_enrollment_code_is_used_when_provided(): void
    {
        $program = $this->makeProgram(tuition: 1_000_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        $action = app(EnrollStudentInCohortAction::class);

        $enrollment = $action->execute([
            'student_party_id' => 'student-custom-code',
            'cohort_id' => $cohort->id,
            'enrollment_code' => 'ENR-CUSTOM-123',
            'idempotency_key' => 'edu:enr:custom-code:01',
        ]);

        $this->assertEquals('ENR-CUSTOM-123', $enrollment->enrollment_code);
    }

    // ──────────────────────────────────────────────
    // 6. Idempotency: same key, different student (Lines 52-59)
    // ──────────────────────────────────────────────

    public function test_same_key_different_student_throws_conflict(): void
    {
        $program = $this->makeProgram(tuition: 1_000_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        $key = 'edu:enr:conflict-student:01';
        $action = app(EnrollStudentInCohortAction::class);

        $action->execute([
            'student_party_id' => 'original-student',
            'cohort_id' => $cohort->id,
            'idempotency_key' => $key,
        ]);

        $this->expectException(IdempotencyConflictException::class);
        $action->execute([
            'student_party_id' => 'different-student',  // same cohort, different student
            'cohort_id' => $cohort->id,
            'idempotency_key' => $key,
        ]);
    }

    public function test_same_key_same_student_different_cohort_throws_conflict(): void
    {
        $program = $this->makeProgram(tuition: 1_000_000);
        $cohort1 = $this->makeCohort($program, capacity: 10);
        $cohort2 = $this->makeCohort($program, capacity: 10);
        $key = 'edu:enr:conflict-cohort:01';
        $action = app(EnrollStudentInCohortAction::class);

        $action->execute([
            'student_party_id' => 'student-chg-cohort',
            'cohort_id' => $cohort1->id,
            'idempotency_key' => $key,
        ]);

        $this->expectException(IdempotencyConflictException::class);
        $action->execute([
            'student_party_id' => 'student-chg-cohort',  // same student
            'cohort_id' => $cohort2->id,            // different cohort
            'idempotency_key' => $key,
        ]);
    }

    public function test_same_key_same_student_via_user_id_returns_existing(): void
    {
        $user = User::factory()->create();
        $program = $this->makeProgram(tuition: 1_000_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        $key = 'edu:enr:idem-user:01';
        $action = app(EnrollStudentInCohortAction::class);

        // First call: identifies student via user_id
        $e1 = $action->execute([
            'student_user_id' => $user->id,
            'cohort_id' => $cohort->id,
            'idempotency_key' => $key,
        ]);

        // Retry: same user_id → should return same enrollment (isSameStudent via user_id branch)
        $e2 = $action->execute([
            'student_user_id' => $user->id,
            'cohort_id' => $cohort->id,
            'idempotency_key' => $key,
        ]);

        $this->assertEquals($e1->id, $e2->id);
        $this->assertEquals(1, $cohort->fresh()->enrolled_count);
    }

    // ──────────────────────────────────────────────
    // 7. Foreach prerequisite loop actually runs (Lines 78-93)
    // ──────────────────────────────────────────────

    public function test_enrollment_blocked_when_active_certificate_missing_for_prerequisite(): void
    {
        $prereqAction = app(AddProgramPrerequisiteAction::class);

        $progA = $this->makeProgram(tuition: 1_000_000, code: 'PRG-PREREQ-A');
        $progB = $this->makeProgram(tuition: 1_000_000, code: 'PRG-PREREQ-B');

        // B requires A
        $prereqAction->execute(['program_id' => $progB->id, 'prerequisite_program_id' => $progA->id]);

        $cohortB = $this->makeCohort($progB, capacity: 10);
        $action = app(EnrollStudentInCohortAction::class);

        // Student has no cert for A → must throw
        $this->expectException(PrerequisiteNotMetException::class);
        $action->execute([
            'student_party_id' => 'student-no-prereq',
            'cohort_id' => $cohortB->id,
            'idempotency_key' => 'edu:enr:prereq-block:01',
        ]);
    }

    public function test_enrollment_succeeds_when_active_certificate_satisfies_prerequisite(): void
    {
        $prereqAction = app(AddProgramPrerequisiteAction::class);
        $signer = app(CertificateQrSigner::class);

        $progA = $this->makeProgram(tuition: 1_000_000, code: 'PRG-OK-A');
        $progB = $this->makeProgram(tuition: 1_000_000, code: 'PRG-OK-B');

        $prereqAction->execute(['program_id' => $progB->id, 'prerequisite_program_id' => $progA->id]);

        $cohortB = $this->makeCohort($progB, capacity: 10);

        $user = User::factory()->create();
        $certId = (string) Str::uuid();

        // Issue active cert for progA
        EduCertificate::create([
            'id' => $certId,
            'certificate_number' => 'CERT-2026-PRG-OK-A-000001',
            'program_id' => $progA->id,
            'student_user_id' => $user->id,
            'exam_attempt_id' => null,
            'issued_at' => now()->subMonths(1),
            'expires_at' => now()->addMonths(11),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', 'prereq-ok-cert-01'),
            'qr_token' => $signer->generateToken($certId, 'v1'),
            'status' => 'ACTIVE',
        ]);

        $action = app(EnrollStudentInCohortAction::class);
        $enrollment = $action->execute([
            'student_user_id' => $user->id,
            'cohort_id' => $cohortB->id,
            'idempotency_key' => 'edu:enr:prereq-ok:01',
        ]);

        $this->assertEquals('ENROLLED', $enrollment->status);
    }

    // ──────────────────────────────────────────────
    // 8. Tuition resolved from program (Line 99)
    // ──────────────────────────────────────────────

    public function test_tuition_defaults_to_program_fee_when_amount_paid_not_given(): void
    {
        $program = $this->makeProgram(tuition: 4_250_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        $action = app(EnrollStudentInCohortAction::class);

        $enrollment = $action->execute([
            'student_party_id' => 'student-default-fee',
            'cohort_id' => $cohort->id,
            'idempotency_key' => 'edu:enr:default-fee:01',
        ]);

        $this->assertEquals(4_250_000, $enrollment->amount_paid_minor,
            'amount_paid_minor must equal program tuition_fee_minor when not explicitly provided.');
    }

    public function test_tuition_can_be_overridden_by_amount_paid_minor_param(): void
    {
        $program = $this->makeProgram(tuition: 4_250_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        $action = app(EnrollStudentInCohortAction::class);

        $enrollment = $action->execute([
            'student_party_id' => 'student-override-fee',
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 3_000_000,
            'idempotency_key' => 'edu:enr:override-fee:01',
        ]);

        $this->assertEquals(3_000_000, $enrollment->amount_paid_minor,
            'Explicit amount_paid_minor must override program tuition.');
    }

    // ──────────────────────────────────────────────
    // 9. enrolled_count incremented after create (Line 137)
    // ──────────────────────────────────────────────

    public function test_cohort_enrolled_count_incremented_for_each_enrollment(): void
    {
        $program = $this->makeProgram(tuition: 1_000_000);
        $cohort = $this->makeCohort($program, capacity: 5);
        $action = app(EnrollStudentInCohortAction::class);

        $this->assertEquals(0, $cohort->enrolled_count);

        $action->execute([
            'student_party_id' => 'student-count-01',
            'cohort_id' => $cohort->id,
            'idempotency_key' => 'edu:enr:count:01',
        ]);
        $this->assertEquals(1, $cohort->fresh()->enrolled_count);

        $action->execute([
            'student_party_id' => 'student-count-02',
            'cohort_id' => $cohort->id,
            'idempotency_key' => 'edu:enr:count:02',
        ]);
        $this->assertEquals(2, $cohort->fresh()->enrolled_count);
    }

    // ──────────────────────────────────────────────
    // 10. Audit trail recorded (Lines 155-160)
    // ──────────────────────────────────────────────

    public function test_audit_trail_entry_created_for_enrollment(): void
    {
        $program = $this->makeProgram(tuition: 2_500_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        $action = app(EnrollStudentInCohortAction::class);

        $action->execute([
            'student_party_id' => 'student-audit-check',
            'cohort_id' => $cohort->id,
            'idempotency_key' => 'edu:enr:audit:01',
        ]);

        $auditEntry = DB::table('core_audit_logs')
            ->where('action', 'edu_student_enrolled')
            ->where('auditable_type', 'LIKE', '%EduEnrollment%')
            ->first();

        $this->assertNotNull($auditEntry, 'core_audit_logs must contain edu_student_enrolled entry.');
        $decoded = json_decode($auditEntry->new_values, true);
        $this->assertArrayHasKey('cohort_code', $decoded, 'Audit must record cohort_code.');
        $this->assertArrayHasKey('amount_paid', $decoded, 'Audit must record amount_paid.');
        $this->assertEquals(2_500_000, $decoded['amount_paid']);
    }

    // ──────────────────────────────────────────────
    // 11. Enrollment records idempotency_key in DB (Line 134)
    // ──────────────────────────────────────────────

    public function test_idempotency_key_persisted_to_enrollment_record(): void
    {
        $program = $this->makeProgram(tuition: 1_000_000);
        $cohort = $this->makeCohort($program, capacity: 10);
        $key = 'edu:enr:persist-key:01';
        $action = app(EnrollStudentInCohortAction::class);

        $enrollment = $action->execute([
            'student_party_id' => 'student-persist-key',
            'cohort_id' => $cohort->id,
            'idempotency_key' => $key,
        ]);

        $this->assertEquals($key, $enrollment->idempotency_key);
        $this->assertEquals($key, EduEnrollment::find($enrollment->id)->idempotency_key);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    protected function makeProgram(int $tuition, string $code = ''): EduProgram
    {
        return EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => $code ?: 'PRG-MUT-'.strtoupper(Str::random(6)),
            'title' => 'Mutation Coverage Program',
            'industry_sector' => 'GENERAL',
            'tuition_fee_minor' => $tuition,
            'passing_score' => 70,
            'validity_months' => 12,
            'status' => 'ACTIVE',
        ]);
    }

    protected function makeCohort(EduProgram $program, int $capacity): EduCohort
    {
        return EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-MUT-'.strtoupper(Str::random(6)),
            'program_id' => $program->id,
            'instructor_party_id' => 'instructor-test',
            'max_capacity' => $capacity,
            'enrolled_count' => 0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'ACTIVE',
        ]);
    }

    protected function makeQuota(
        EduProgram $program,
        string $corporateClientId,
        int $total,
        int $unitPrice,
    ): EduCorporateQuota {
        return EduCorporateQuota::create([
            'id' => (string) Str::uuid(),
            'corporate_client_id' => $corporateClientId,
            'program_id' => $program->id,
            'quota_total' => $total,
            'quota_used' => 0,
            'unit_price_minor' => $unitPrice,
            'status' => 'ACTIVE',
        ]);
    }
}
