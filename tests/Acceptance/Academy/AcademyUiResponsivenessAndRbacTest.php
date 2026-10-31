<?php

declare(strict_types=1);

namespace Tests\Acceptance\Academy;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Edu\database\seeders\EduRbacSeeder;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduExamAttempt;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Edu\Domain\Services\CertificateQrSigner;
use Tests\TestCase;

class AcademyUiResponsivenessAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(EduRbacSeeder::class);
    }

    public function test_academy_admin_can_access_admin_dashboard_and_manage_programs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('academy_admin');

        $response = $this->actingAs($admin)->get('/academy/admin/programs');
        $response->assertStatus(200);

        $postResponse = $this->actingAs($admin)->post('/academy/admin/programs', [
            'program_code' => 'PRG-ADM-01',
            'title' => 'Admin Created Program',
            'industry_sector' => 'IT',
            'tuition_fee_minor' => 5_000_000,
            'passing_score' => 80,
            'validity_months' => 24,
        ]);

        $postResponse->assertRedirect();
        $this->assertDatabaseHas('edu_programs', ['program_code' => 'PRG-ADM-01']);
    }

    public function test_trainer_cannot_create_programs_but_can_grade_attempts(): void
    {
        $trainer = User::factory()->create();
        $trainer->assignRole('trainer');

        // Forbidden from program creation
        $response = $this->actingAs($trainer)->post('/academy/admin/programs', [
            'program_code' => 'PRG-TRN-01',
            'title' => 'Trainer Program',
            'industry_sector' => 'IT',
            'tuition_fee_minor' => 5_000_000,
            'passing_score' => 80,
            'validity_months' => 24,
        ]);
        $response->assertStatus(403);
    }

    public function test_student_cannot_grade_attempts_or_access_admin_portal(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');

        $response = $this->actingAs($student)->get('/academy/admin/programs');
        $response->assertStatus(403);
    }

    public function test_anti_idor_student_cannot_view_another_student_certificate(): void
    {
        $studentA = User::factory()->create();
        $studentA->assignRole('student');

        $studentB = User::factory()->create();
        $studentB->assignRole('student');

        $certB = $this->createCertificateForUser($studentB);

        // Student A tries to view Student B's private certificate detail
        $response = $this->actingAs($studentA)->get("/academy/student/certificates/{$certB->id}");
        $response->assertStatus(403);
    }

    public function test_anti_idor_student_cannot_view_another_student_exam_attempt(): void
    {
        $studentA = User::factory()->create();
        $studentA->assignRole('student');

        $studentB = User::factory()->create();
        $studentB->assignRole('student');

        $attemptB = $this->createExamAttemptForUser($studentB);

        $response = $this->actingAs($studentA)->get("/academy/student/attempts/{$attemptB->id}");
        $response->assertStatus(403);
    }

    public function test_public_certificate_verification_page_includes_responsive_viewport(): void
    {
        $user = User::factory()->create();
        $cert = $this->createCertificateForUser($user);

        $response = $this->get("/academy/certificates/verify?token={$cert->qr_token}");
        $response->assertStatus(200);

        // Responsive mobile viewport tag verification (375px compatibility)
        $content = $response->getContent();
        $this->assertStringContainsString('name="viewport"', $content);
        $this->assertStringContainsString('width=device-width', $content);
        $this->assertStringContainsString($cert->certificate_number, $content);
    }

    public function test_unauthenticated_user_is_redirected_from_student_portal(): void
    {
        $response = $this->get('/academy/student/dashboard');
        $response->assertRedirect('/login');
    }

    private function createCertificateForUser(User $user): EduCertificate
    {
        $prog = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-'.strtoupper(Str::random(6)),
            'title' => 'Security Operations',
            'industry_sector' => 'IT',
            'tuition_fee_minor' => 3_000_000,
            'passing_score' => 80,
            'validity_months' => 24,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-'.strtoupper(Str::random(6)),
            'program_id' => $prog->id,
            'instructor_party_id' => 'INS-01',
            'max_capacity' => 25,
            'enrolled_count' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
            'status' => 'COMPLETED',
        ]);

        $attempt = $this->createExamAttemptForUser($user, $cohort);
        $signer = app(CertificateQrSigner::class);
        $uuid = (string) Str::uuid();

        return EduCertificate::create([
            'id' => $uuid,
            'certificate_number' => 'CERT-2026-'.$prog->program_code.'-000001',
            'program_id' => $prog->id,
            'student_user_id' => $user->id,
            'exam_attempt_id' => $attempt->id,
            'issued_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMonths(24),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', $uuid),
            'qr_token' => $signer->generateToken($uuid, 'v1'),
            'status' => 'ACTIVE',
        ]);
    }

    private function createExamAttemptForUser(User $user, ?EduCohort $cohort = null): EduExamAttempt
    {
        if (! $cohort) {
            $prog = EduProgram::create([
                'id' => (string) Str::uuid(),
                'program_code' => 'PRG-'.strtoupper(Str::random(6)),
                'title' => 'Basic Operations',
                'industry_sector' => 'IT',
                'tuition_fee_minor' => 1_000_000,
                'passing_score' => 75,
                'validity_months' => 24,
                'status' => 'ACTIVE',
            ]);

            $cohort = EduCohort::create([
                'id' => (string) Str::uuid(),
                'cohort_code' => 'COH-'.strtoupper(Str::random(6)),
                'program_id' => $prog->id,
                'instructor_party_id' => 'INS-01',
                'max_capacity' => 20,
                'enrolled_count' => 1,
                'start_date' => '2026-01-01',
                'end_date' => '2026-02-01',
                'status' => 'ACTIVE',
            ]);
        }

        $enr = EduEnrollment::create([
            'id' => (string) Str::uuid(),
            'enrollment_code' => 'ENR-'.strtoupper(Str::random(6)),
            'student_user_id' => $user->id,
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 1_000_000,
            'status' => 'IN_PROGRESS',
        ]);

        return EduExamAttempt::create([
            'id' => (string) Str::uuid(),
            'attempt_code' => 'ATT-'.strtoupper(Str::random(6)),
            'student_user_id' => $user->id,
            'enrollment_id' => $enr->id,
            'score' => 88,
            'passing_score' => 75,
            'is_passed' => true,
            'proctor_verification_hash' => hash('sha256', "proctor-{$user->id}"),
            'status' => 'COMPLETED',
        ]);
    }
}
