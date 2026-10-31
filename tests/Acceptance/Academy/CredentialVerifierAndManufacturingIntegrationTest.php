<?php

declare(strict_types=1);

namespace Tests\Acceptance\Academy;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Edu\Contracts\CredentialVerifier;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduExamAttempt;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Edu\Domain\Services\CertificateQrSigner;
use Modules\Manufacturing\Application\Actions\IssueMaintenanceLotoSafetyPermitAction;
use Modules\Manufacturing\Domain\Exceptions\SafetyPermitUnauthorizedException;
use Modules\Manufacturing\Domain\Models\WorkCenter;
use Tests\TestCase;

class CredentialVerifierAndManufacturingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_credential_verifier_contract_is_bound_in_container(): void
    {
        $verifier = app(CredentialVerifier::class);
        $this->assertInstanceOf(CredentialVerifier::class, $verifier);
    }

    public function test_credential_verifier_returns_true_for_active_certificate(): void
    {
        $user = User::factory()->create();
        $this->createCertificateForUser($user, 'k3_loto', 'ACTIVE', Carbon::now()->subMonths(1), Carbon::now()->addMonths(11));

        $verifier = app(CredentialVerifier::class);
        $result = $verifier->hasValid($user->id, 'k3_loto', CarbonImmutable::now());

        $this->assertTrue($result);
    }

    public function test_credential_verifier_returns_false_when_no_certificate_exists(): void
    {
        $user = User::factory()->create();
        $verifier = app(CredentialVerifier::class);

        $result = $verifier->hasValid($user->id, 'k3_loto', CarbonImmutable::now());
        $this->assertFalse($result);
    }

    public function test_credential_verifier_returns_false_when_certificate_is_expired(): void
    {
        $user = User::factory()->create();
        $this->createCertificateForUser($user, 'k3_loto', 'EXPIRED', Carbon::now()->subMonths(14), Carbon::now()->subMonths(2));

        $verifier = app(CredentialVerifier::class);
        $result = $verifier->hasValid($user->id, 'k3_loto', CarbonImmutable::now());

        $this->assertFalse($result);
    }

    public function test_credential_verifier_returns_false_when_certificate_is_revoked(): void
    {
        $user = User::factory()->create();
        $this->createCertificateForUser($user, 'k3_loto', 'REVOKED', Carbon::now()->subMonths(1), Carbon::now()->addMonths(11));

        $verifier = app(CredentialVerifier::class);
        $result = $verifier->hasValid($user->id, 'k3_loto', CarbonImmutable::now());

        $this->assertFalse($result);
    }

    public function test_manufacturing_loto_permit_succeeds_when_technician_has_active_k3_loto_certificate(): void
    {
        $technician = User::factory()->create();
        $this->createCertificateForUser($technician, 'k3_loto', 'ACTIVE', Carbon::now()->subMonths(1), Carbon::now()->addMonths(11));

        $workCenter = $this->createSampleWorkCenter();
        $action = app(IssueMaintenanceLotoSafetyPermitAction::class);

        $validFrom = now();
        $validUntil = now()->addHours(8);

        $permit = $action->execute(
            workCenter: $workCenter,
            permitType: 'loto',
            hazards: '380V High Voltage Feed & Pressurized Pneumatics',
            controls: 'Dual padlock LOTO applied, main isolation breaker locked, bleed valve open',
            validFrom: $validFrom,
            validUntil: $validUntil,
            requestedByUserId: $technician->id
        );

        $this->assertNotNull($permit);
        $this->assertEquals('active', $permit->status);
        $this->assertEquals($technician->id, $permit->requested_by_user_id);
    }

    public function test_manufacturing_loto_permit_throws_unauthorized_when_technician_lacks_certificate(): void
    {
        $unqualifiedUser = User::factory()->create();
        $workCenter = $this->createSampleWorkCenter();
        $action = app(IssueMaintenanceLotoSafetyPermitAction::class);

        $this->expectException(SafetyPermitUnauthorizedException::class);
        $this->expectExceptionMessage('lacks active k3_loto');

        $action->execute(
            workCenter: $workCenter,
            permitType: 'loto',
            hazards: '380V High Voltage Feed',
            controls: 'Padlock installed',
            validFrom: now(),
            validUntil: now()->addHours(4),
            requestedByUserId: $unqualifiedUser->id
        );
    }

    public function test_manufacturing_loto_permit_throws_unauthorized_when_certificate_is_expired(): void
    {
        $technician = User::factory()->create();
        $this->createCertificateForUser($technician, 'k3_loto', 'EXPIRED', Carbon::now()->subMonths(14), Carbon::now()->subMonths(1));

        $workCenter = $this->createSampleWorkCenter();
        $action = app(IssueMaintenanceLotoSafetyPermitAction::class);

        $this->expectException(SafetyPermitUnauthorizedException::class);
        $this->expectExceptionMessage('lacks active k3_loto');

        $action->execute(
            workCenter: $workCenter,
            permitType: 'loto',
            hazards: '380V High Voltage Feed',
            controls: 'Padlock installed',
            validFrom: now(),
            validUntil: now()->addHours(4),
            requestedByUserId: $technician->id
        );
    }

    public function test_credential_verifier_patch_file_exists_and_is_non_empty(): void
    {
        $patchPath = base_path('docs/patches/multilini-3/edu-credential-verifier.patch');
        $this->assertFileExists($patchPath, 'Edu credential verifier patch file must exist for Mining and AutoServe (Z13)');
        $content = file_get_contents($patchPath);
        $this->assertStringContainsString('CredentialVerifier', $content);
        $this->assertStringContainsString('hasValid', $content);
    }

    private function createCertificateForUser(
        User $user,
        string $programCode,
        string $status,
        Carbon $issuedAt,
        Carbon $expiresAt
    ): EduCertificate {
        $program = EduProgram::firstOrCreate(
            ['program_code' => $programCode],
            [
                'id' => (string) Str::uuid(),
                'title' => 'K3 Lockout Tagout Hazardous Energy Control',
                'industry_sector' => 'MANUFACTURING',
                'tuition_fee_minor' => 3_500_000,
                'passing_score' => 80,
                'validity_months' => 12,
                'status' => 'ACTIVE',
            ]
        );

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-'.strtoupper(Str::random(8)),
            'program_id' => $program->id,
            'instructor_party_id' => 'INS-K3',
            'max_capacity' => 30,
            'enrolled_count' => 1,
            'start_date' => $issuedAt->copy()->subDays(14)->toDateString(),
            'end_date' => $issuedAt->toDateString(),
            'status' => 'COMPLETED',
        ]);

        $enrollment = EduEnrollment::create([
            'id' => (string) Str::uuid(),
            'enrollment_code' => 'ENR-'.strtoupper(Str::random(8)),
            'student_user_id' => $user->id,
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 3_500_000,
            'status' => 'GRADUATED',
        ]);

        $attempt = EduExamAttempt::create([
            'id' => (string) Str::uuid(),
            'attempt_code' => 'ATT-'.strtoupper(Str::random(8)),
            'student_user_id' => $user->id,
            'enrollment_id' => $enrollment->id,
            'score' => 88,
            'passing_score' => 80,
            'is_passed' => true,
            'proctor_verification_hash' => hash('sha256', 'proctor-k3'),
            'status' => 'COMPLETED',
        ]);

        $signer = app(CertificateQrSigner::class);
        $certUuid = (string) Str::uuid();
        $qrToken = $signer->generateToken($certUuid, 'v1');

        return EduCertificate::create([
            'id' => $certUuid,
            'certificate_number' => 'CERT-'.$issuedAt->year.'-'.$programCode.'-000001',
            'program_id' => $program->id,
            'student_user_id' => $user->id,
            'exam_attempt_id' => $attempt->id,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', $certUuid),
            'qr_token' => $qrToken,
            'status' => $status,
        ]);
    }

    private function createSampleWorkCenter(): WorkCenter
    {
        return WorkCenter::create([
            'plant_id' => 1,
            'code' => 'WC-SMELT-01',
            'name' => 'Smelting Furnace 01',
            'type' => 'machine',
            'capacity_per_day_hours' => 24,
            'cost_per_hour' => 150000,
            'status' => 'active',
        ]);
    }
}
