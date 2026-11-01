<?php

declare(strict_types=1);

namespace Tests\Acceptance\Academy;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduExamAttempt;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Edu\Domain\Services\CertificateQrSigner;
use Tests\TestCase;

class PublicQrVerificationAndAntiPiiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        Config::set('app.key', 'base64:uN47fR2b8xQZ2J9Wq7LkM4p0XvY1Zr8sTb6Wd9EfGh4=');
    }

    public function test_qr_token_generation_produces_three_part_hmac_format(): void
    {
        $certUuid = (string) Str::uuid();
        $keyId = 'v1';

        $signer = app(CertificateQrSigner::class);
        $token = $signer->generateToken($certUuid, $keyId);

        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'QR token must have format uuid.key_id.hmac');
        $this->assertEquals($certUuid, $parts[0]);
        $this->assertEquals($keyId, $parts[1]);
        $this->assertEquals(64, strlen($parts[2]), 'HMAC-SHA256 hex string must be 64 characters');

        $this->assertTrue($signer->verifyToken($token));
    }

    public function test_public_verification_returns_valid_details_without_pii(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Rahardjo',
            'email' => 'budi.rahardjo.secret@conglom.test',
        ]);

        $program = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-SEC-01',
            'title' => 'Certified Industrial Cybersecurity Specialist',
            'industry_sector' => 'TELECOM',
            'tuition_fee_minor' => 10_000_000,
            'passing_score' => 80,
            'validity_months' => 24,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-SEC-2026-01',
            'program_id' => $program->id,
            'instructor_party_id' => 'INS-99',
            'max_capacity' => 20,
            'enrolled_count' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-01',
            'status' => 'ACTIVE',
        ]);

        $enrollment = EduEnrollment::create([
            'id' => (string) Str::uuid(),
            'enrollment_code' => 'ENR-SEC-001',
            'student_user_id' => $user->id,
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 10_000_000,
            'status' => 'GRADUATED',
        ]);

        $attempt = EduExamAttempt::create([
            'id' => (string) Str::uuid(),
            'attempt_code' => 'ATT-SEC-001',
            'student_user_id' => $user->id,
            'enrollment_id' => $enrollment->id,
            'score' => 92,
            'passing_score' => 80,
            'is_passed' => true,
            'proctor_verification_hash' => hash('sha256', 'proctor-valid'),
            'status' => 'COMPLETED',
        ]);

        $certUuid = (string) Str::uuid();
        $signer = app(CertificateQrSigner::class);
        $qrToken = $signer->generateToken($certUuid, 'v1');

        $cert = EduCertificate::create([
            'id' => $certUuid,
            'certificate_number' => 'CERT-2026-PRG-SEC-01-000001',
            'program_id' => $program->id,
            'student_user_id' => $user->id,
            'exam_attempt_id' => $attempt->id,
            'issued_at' => Carbon::now()->subDays(10),
            'expires_at' => Carbon::now()->addMonths(24),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', 'payload-canonical'),
            'qr_token' => $qrToken,
            'status' => 'ACTIVE',
        ]);

        $response = $this->getJson("/api/v1/edu/certificates/verify?token={$qrToken}");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals('ACTIVE', $data['status']);
        $this->assertEquals('PRG-SEC-01', $data['program_code']);
        $this->assertEquals('Certified Industrial Cybersecurity Specialist', $data['program_title']);
        $this->assertEquals('CERT-2026-PRG-SEC-01-000001', $data['certificate_number']);
        $this->assertNotEmpty($data['issued_at']);
        $this->assertNotEmpty($data['expires_at']);

        // Anti-PII verification: ensure name, email, user_id are NOT exposed
        $responseContent = $response->getContent();
        $this->assertStringNotContainsString('Budi Rahardjo', $responseContent);
        $this->assertStringNotContainsString('budi.rahardjo.secret@conglom.test', $responseContent);
        $this->assertArrayNotHasKey('student_user_id', $data);
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('student_name', $data);
    }

    public function test_tampered_qr_token_signature_returns_422(): void
    {
        $certUuid = (string) Str::uuid();
        $signer = app(CertificateQrSigner::class);
        $validToken = $signer->generateToken($certUuid, 'v1');

        $tamperedToken = $validToken.'tampered';

        $response = $this->getJson("/api/v1/edu/certificates/verify?token={$tamperedToken}");
        $response->assertStatus(422);
    }

    public function test_unknown_key_id_returns_422(): void
    {
        $certUuid = (string) Str::uuid();
        $invalidKeyToken = "{$certUuid}.unknown_key.1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef";

        $response = $this->getJson("/api/v1/edu/certificates/verify?token={$invalidKeyToken}");
        $response->assertStatus(422);
    }

    public function test_expired_certificate_returns_expired_status(): void
    {
        $user = User::factory()->create();
        $program = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-EXP-01',
            'title' => 'Expired Course',
            'industry_sector' => 'ENERGY',
            'tuition_fee_minor' => 1_000_000,
            'passing_score' => 70,
            'validity_months' => 6,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-EXP-01',
            'program_id' => $program->id,
            'instructor_party_id' => 'INS-01',
            'max_capacity' => 10,
            'enrolled_count' => 1,
            'start_date' => '2025-01-01',
            'end_date' => '2025-02-01',
            'status' => 'COMPLETED',
        ]);

        $enrollment = EduEnrollment::create([
            'id' => (string) Str::uuid(),
            'enrollment_code' => 'ENR-EXP-001',
            'student_user_id' => $user->id,
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 1_000_000,
            'status' => 'GRADUATED',
        ]);

        $attempt = EduExamAttempt::create([
            'id' => (string) Str::uuid(),
            'attempt_code' => 'ATT-EXP-001',
            'student_user_id' => $user->id,
            'enrollment_id' => $enrollment->id,
            'score' => 85,
            'passing_score' => 70,
            'is_passed' => true,
            'proctor_verification_hash' => hash('sha256', 'proctor-ok'),
            'status' => 'COMPLETED',
        ]);

        $certUuid = (string) Str::uuid();
        $signer = app(CertificateQrSigner::class);
        $qrToken = $signer->generateToken($certUuid, 'v1');

        EduCertificate::create([
            'id' => $certUuid,
            'certificate_number' => 'CERT-2025-PRG-EXP-01-000001',
            'program_id' => $program->id,
            'student_user_id' => $user->id,
            'exam_attempt_id' => $attempt->id,
            'issued_at' => Carbon::now()->subMonths(12),
            'expires_at' => Carbon::now()->subMonths(6),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', 'hash-exp'),
            'qr_token' => $qrToken,
            'status' => 'EXPIRED',
        ]);

        $response = $this->getJson("/api/v1/edu/certificates/verify?token={$qrToken}");
        $response->assertStatus(200);
        $this->assertEquals('EXPIRED', $response->json('data.status'));
    }

    public function test_revoked_certificate_returns_revoked_status(): void
    {
        $user = User::factory()->create();
        $program = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-RVK-01',
            'title' => 'Revoked Safety Cert',
            'industry_sector' => 'MANUFACTURING',
            'tuition_fee_minor' => 2_000_000,
            'passing_score' => 80,
            'validity_months' => 12,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-RVK-01',
            'program_id' => $program->id,
            'instructor_party_id' => 'INS-02',
            'max_capacity' => 10,
            'enrolled_count' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
            'status' => 'COMPLETED',
        ]);

        $enrollment = EduEnrollment::create([
            'id' => (string) Str::uuid(),
            'enrollment_code' => 'ENR-RVK-001',
            'student_user_id' => $user->id,
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 2_000_000,
            'status' => 'GRADUATED',
        ]);

        $attempt = EduExamAttempt::create([
            'id' => (string) Str::uuid(),
            'attempt_code' => 'ATT-RVK-001',
            'student_user_id' => $user->id,
            'enrollment_id' => $enrollment->id,
            'score' => 85,
            'passing_score' => 80,
            'is_passed' => true,
            'proctor_verification_hash' => hash('sha256', 'proctor-rev'),
            'status' => 'COMPLETED',
        ]);

        $certUuid = (string) Str::uuid();
        $signer = app(CertificateQrSigner::class);
        $qrToken = $signer->generateToken($certUuid, 'v1');

        EduCertificate::create([
            'id' => $certUuid,
            'certificate_number' => 'CERT-2026-PRG-RVK-01-000001',
            'program_id' => $program->id,
            'student_user_id' => $user->id,
            'exam_attempt_id' => $attempt->id,
            'issued_at' => Carbon::now()->subDays(10),
            'expires_at' => Carbon::now()->addMonths(12),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', 'hash-rvk'),
            'qr_token' => $qrToken,
            'status' => 'REVOKED',
        ]);

        $response = $this->getJson("/api/v1/edu/certificates/verify?token={$qrToken}");
        $response->assertStatus(200);
        $this->assertEquals('REVOKED', $response->json('data.status'));
    }

    public function test_non_existent_token_returns_404(): void
    {
        $nonExistentUuid = (string) Str::uuid();
        $signer = app(CertificateQrSigner::class);
        $token = $signer->generateToken($nonExistentUuid, 'v1');

        $response = $this->getJson("/api/v1/edu/certificates/verify?token={$token}");
        $response->assertStatus(404);
    }
}
