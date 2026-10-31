<?php

declare(strict_types=1);

namespace Tests\Acceptance\Academy;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Edu\Application\Actions\SettleHeadhunterFeeAction;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduCorporateQuota;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduExamAttempt;
use Modules\Edu\Domain\Models\EduJobOpening;
use Modules\Edu\Domain\Models\EduPlacement;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Edu\Domain\Services\CertificateQrSigner;
use Modules\Edu\Ledger\EduAccounts;
use Modules\Ledger\Domain\Models\LedgerEntry;
use Tests\TestCase;

class AcademyAuditAndTwoSourceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_baseline_edu_audit_passes_cleanly(): void
    {
        $exitCode = Artisan::call('edu:audit');
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode, 'Baseline audit must exit 0');
        $this->assertStringContainsString('0 discrepancies found', $output);
    }

    public function test_edu_audit_detects_corrupted_overcapacity_cohort_fixture(): void
    {
        $program = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-OVER-01',
            'title' => 'Overcapacity Program',
            'industry_sector' => 'GENERAL',
            'tuition_fee_minor' => 1_000_000,
            'passing_score' => 70,
            'validity_months' => 12,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-OVER-01',
            'program_id' => $program->id,
            'instructor_party_id' => 'INS-01',
            'max_capacity' => 20,
            'enrolled_count' => 25, // Corrupted: enrolled > max_capacity
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
            'status' => 'ACTIVE',
        ]);

        $exitCode = Artisan::call('edu:audit');
        $output = Artisan::output();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('COHORT_OVERCAPACITY', $output);
    }

    public function test_edu_audit_detects_certificate_count_exceeding_passed_exam_attempts(): void
    {
        $user = User::factory()->create();
        $program = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-GHOST-01',
            'title' => 'Ghost Certificate Program',
            'industry_sector' => 'GENERAL',
            'tuition_fee_minor' => 1_000_000,
            'passing_score' => 75,
            'validity_months' => 12,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-GHOST-01',
            'program_id' => $program->id,
            'instructor_party_id' => 'INS-01',
            'max_capacity' => 10,
            'enrolled_count' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
            'status' => 'ACTIVE',
        ]);

        $enrollment = EduEnrollment::create([
            'id' => (string) Str::uuid(),
            'enrollment_code' => 'ENR-GHOST-001',
            'student_user_id' => $user->id,
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 1_000_000,
            'status' => 'GRADUATED',
        ]);

        // Attempt was FAILED (score 50 < passing 75)
        $attempt = EduExamAttempt::create([
            'id' => (string) Str::uuid(),
            'attempt_code' => 'ATT-GHOST-001',
            'student_user_id' => $user->id,
            'enrollment_id' => $enrollment->id,
            'score' => 50,
            'passing_score' => 75,
            'is_passed' => false,
            'proctor_verification_hash' => hash('sha256', 'fail'),
            'status' => 'COMPLETED',
        ]);

        $signer = app(CertificateQrSigner::class);
        $certUuid = (string) Str::uuid();

        // Fraudulent certificate issued despite failed attempt
        EduCertificate::create([
            'id' => $certUuid,
            'certificate_number' => 'CERT-2026-PRG-GHOST-01-000001',
            'program_id' => $program->id,
            'student_user_id' => $user->id,
            'exam_attempt_id' => $attempt->id,
            'issued_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMonths(12),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', 'fraud'),
            'qr_token' => $signer->generateToken($certUuid, 'v1'),
            'status' => 'ACTIVE',
        ]);

        $exitCode = Artisan::call('edu:audit');
        $output = Artisan::output();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('CERTIFICATE_WITHOUT_PASSED_ATTEMPT', $output);
    }

    public function test_edu_audit_detects_gap_in_certificate_sequence_numbering(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $program = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-GAP-01',
            'title' => 'Gap Sequence Program',
            'industry_sector' => 'ENERGY',
            'tuition_fee_minor' => 1_000_000,
            'passing_score' => 70,
            'validity_months' => 12,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-GAP-01',
            'program_id' => $program->id,
            'instructor_party_id' => 'INS-01',
            'max_capacity' => 10,
            'enrolled_count' => 2,
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
            'status' => 'ACTIVE',
        ]);

        $att1 = $this->createPassedAttempt($user1, $cohort);
        $att2 = $this->createPassedAttempt($user2, $cohort);

        $signer = app(CertificateQrSigner::class);

        // Sequence 000001
        $c1 = (string) Str::uuid();
        EduCertificate::create([
            'id' => $c1,
            'certificate_number' => 'CERT-2026-PRG-GAP-01-000001',
            'program_id' => $program->id,
            'student_user_id' => $user1->id,
            'exam_attempt_id' => $att1->id,
            'issued_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMonths(12),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', 'c1'),
            'qr_token' => $signer->generateToken($c1, 'v1'),
            'status' => 'ACTIVE',
        ]);

        // Sequence 000003 (Gap: 000002 is missing!)
        $c2 = (string) Str::uuid();
        EduCertificate::create([
            'id' => $c2,
            'certificate_number' => 'CERT-2026-PRG-GAP-01-000003',
            'program_id' => $program->id,
            'student_user_id' => $user2->id,
            'exam_attempt_id' => $att2->id,
            'issued_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMonths(12),
            'prev_hash' => hash('sha256', 'c1'),
            'certificate_hash' => hash('sha256', 'c2'),
            'qr_token' => $signer->generateToken($c2, 'v1'),
            'status' => 'ACTIVE',
        ]);

        $exitCode = Artisan::call('edu:audit');
        $output = Artisan::output();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('CERTIFICATE_SEQUENCE_GAP', $output);
    }

    public function test_edu_audit_detects_broken_hash_chain_link(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $program = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-HASH-01',
            'title' => 'Hash Chain Program',
            'industry_sector' => 'IT',
            'tuition_fee_minor' => 1_000_000,
            'passing_score' => 70,
            'validity_months' => 12,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-HASH-01',
            'program_id' => $program->id,
            'instructor_party_id' => 'INS-01',
            'max_capacity' => 10,
            'enrolled_count' => 2,
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
            'status' => 'ACTIVE',
        ]);

        $att1 = $this->createPassedAttempt($user1, $cohort);
        $att2 = $this->createPassedAttempt($user2, $cohort);

        $signer = app(CertificateQrSigner::class);

        $c1 = (string) Str::uuid();
        $hash1 = hash('sha256', 'actual-valid-hash-1');
        EduCertificate::create([
            'id' => $c1,
            'certificate_number' => 'CERT-2026-PRG-HASH-01-000001',
            'program_id' => $program->id,
            'student_user_id' => $user1->id,
            'exam_attempt_id' => $att1->id,
            'issued_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMonths(12),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => $hash1,
            'qr_token' => $signer->generateToken($c1, 'v1'),
            'status' => 'ACTIVE',
        ]);

        // Cert 2 has corrupted prev_hash (not matching hash1)
        $c2 = (string) Str::uuid();
        EduCertificate::create([
            'id' => $c2,
            'certificate_number' => 'CERT-2026-PRG-HASH-01-000002',
            'program_id' => $program->id,
            'student_user_id' => $user2->id,
            'exam_attempt_id' => $att2->id,
            'issued_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMonths(12),
            'prev_hash' => hash('sha256', 'wrong-tampered-prev-hash'),
            'certificate_hash' => hash('sha256', 'c2'),
            'qr_token' => $signer->generateToken($c2, 'v1'),
            'status' => 'ACTIVE',
        ]);

        $exitCode = Artisan::call('edu:audit');
        $output = Artisan::output();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('CERTIFICATE_HASH_CHAIN_BROKEN', $output);
    }

    public function test_edu_audit_detects_corporate_quota_used_mismatch(): void
    {
        $program = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-QUOTA-01',
            'title' => 'Quota Audit Program',
            'industry_sector' => 'IT',
            'tuition_fee_minor' => 1_000_000,
            'passing_score' => 70,
            'validity_months' => 12,
            'status' => 'ACTIVE',
        ]);

        // Corrupted quota: quota_used = 10, but 0 enrollments linked to it
        EduCorporateQuota::create([
            'id' => (string) Str::uuid(),
            'corporate_client_id' => 'CORP-AUDIT-01',
            'program_id' => $program->id,
            'quota_total' => 20,
            'quota_used' => 10,
            'unit_price_minor' => 900_000,
            'status' => 'ACTIVE',
        ]);

        $exitCode = Artisan::call('edu:audit');
        $output = Artisan::output();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('CORPORATE_QUOTA_MISMATCH', $output);
    }

    public function test_edu_audit_detects_headhunter_escrow_ledger_mismatch(): void
    {
        $user = User::factory()->create();
        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-AUDIT-ESC',
            'hiring_entity' => 'CorpAudit',
            'title' => 'Engineer',
            'capacity' => 1,
            'annual_salary_minor' => 100_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-AUD-01',
            'status' => 'OPEN',
        ]);

        $placement = EduPlacement::create([
            'id' => (string) Str::uuid(),
            'placement_code' => 'PLC-AUDIT-001',
            'student_user_id' => $user->id,
            'opening_id' => $opening->id,
            'placed_at' => Carbon::now()->toDateString(),
            'guarantee_days' => 90,
            'status' => 'PLACED',
        ]);

        $action = app(SettleHeadhunterFeeAction::class);
        $action->holdFee($placement); // Creates 15M HELD fee and posts to ledger

        // Sabotage ledger: inject fraudulent entry into escrow account
        LedgerEntry::create([
            'transaction_id' => (string) Str::uuid(),
            'account_code' => EduAccounts::ESCROW_HEADHUNTER_GUARANTEE,
            'amount' => 5_000_000, // Extra 5M phantom credit
            'entry_type' => 'CREDIT',
            'sequence_in_transaction' => 1,
            'occurred_at' => Carbon::now(),
        ]);

        $exitCode = Artisan::call('edu:audit');
        $output = Artisan::output();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('HEADHUNTER_ESCROW_MISMATCH', $output);
    }

    private function createPassedAttempt(User $user, EduCohort $cohort): EduExamAttempt
    {
        $enr = EduEnrollment::create([
            'id' => (string) Str::uuid(),
            'enrollment_code' => 'ENR-'.strtoupper(Str::random(6)),
            'student_user_id' => $user->id,
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 1_000_000,
            'status' => 'GRADUATED',
        ]);

        return EduExamAttempt::create([
            'id' => (string) Str::uuid(),
            'attempt_code' => 'ATT-'.strtoupper(Str::random(6)),
            'student_user_id' => $user->id,
            'enrollment_id' => $enr->id,
            'score' => 90,
            'passing_score' => 70,
            'is_passed' => true,
            'proctor_verification_hash' => hash('sha256', "proctor-{$user->id}"),
            'status' => 'COMPLETED',
        ]);
    }
}
