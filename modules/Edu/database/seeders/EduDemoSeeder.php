<?php

declare(strict_types=1);

namespace Modules\Edu\database\seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Modules\Core\Contracts\ClockInterface;
use Modules\Edu\Application\Actions\EvaluateProctoredExamAttemptAction;
use Modules\Edu\Application\Actions\IssueVerifiedCompetencyCertificateAction;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduProgram;

class EduDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@autoserve.test')->first();
        if (! $admin) {
            return;
        }

        // Idempotency: Jika sertifikat k3_loto aktif sudah ada untuk admin, jangan duplikasi
        $existingCert = EduCertificate::where('student_user_id', $admin->id)
            ->where('skill_competency_code', 'k3_loto')
            ->where('status', 'ACTIVE')
            ->first();

        if ($existingCert !== null) {
            return;
        }

        $clock = app(ClockInterface::class);
        $now = Carbon::parse($clock->{'now'}()->toDateTimeString());

        // 1. Program K3 LOTO
        $program = EduProgram::firstOrCreate(
            ['id' => 'PRG-K3-LOTO'],
            [
                'program_code' => 'k3_loto',
                'title' => 'K3 Lockout/Tagout Safety Certification',
                'industry_sector' => 'MINING_HSE',
                'tuition_fee_minor' => 0,
                'total_sessions' => 1,
                'status' => 'ACTIVE',
            ]
        );

        // 2. Cohort
        $cohort = EduCohort::firstOrCreate(
            ['id' => 'COH-K3-LOTO-ADMIN'],
            [
                'program_id' => $program->id,
                'cohort_code' => 'COH-LOTO-01',
                'instructor_party_id' => 'inst-k3-safety',
                'max_capacity' => 50,
                'enrolled_count' => 0,
                'start_date' => $now->copy()->subMonth()->toDateString(),
                'end_date' => $now->copy()->addMonths(6)->toDateString(),
                'status' => 'ACTIVE',
            ]
        );

        // 3. Enrollment
        $enrollment = EduEnrollment::firstOrCreate(
            ['id' => 'ENR-SEED-ADMIN'],
            [
                'enrollment_code' => 'ENR-SEED-ADMIN',
                'student_user_id' => $admin->id,
                'student_party_id' => "student-{$admin->id}",
                'cohort_id' => $cohort->id,
                'amount_paid_minor' => 0,
                'sessions_attended' => 1,
                'total_sessions' => 1,
                'status' => 'ENROLLED',
                'idempotency_key' => 'seed:enr:admin:loto',
            ]
        );

        if ($cohort->enrolled_count === 0) {
            $cohort->increment('enrolled_count');
        }

        // 4. Proctored Exam Attempt (Lulus, score 90 >= 75)
        $examAction = app(EvaluateProctoredExamAttemptAction::class);
        $attempt = $examAction->execute([
            'enrollment_id' => $enrollment->id,
            'enrollment_code' => $enrollment->enrollment_code,
            'student_user_id' => $admin->id,
            'student_party_uuid' => "student-{$admin->id}",
            'exam_title' => 'K3 Lockout/Tagout Safety Certification Exam',
            'score' => 90,
            'passing_score' => 75,
            'proctor_signature' => 'PROCTOR_SIG_SAFETY_CHIEF',
            'idempotency_key' => 'seed:exam:admin:loto',
        ]);

        // 5. Terbitkan Sertifikat K3 LOTO Nyata (Gapless, Hash Chain, QR Signature)
        $certAction = app(IssueVerifiedCompetencyCertificateAction::class);
        $certAction->execute([
            'enrollment_id' => $enrollment->id,
            'exam_attempt_id' => $attempt->id,
            'program_id' => $program->id,
            'skill_competency_code' => 'k3_loto',
            'issued_at' => $now->copy()->subMonth()->toDateTimeString(),
            'expires_at' => $now->copy()->addYears(2)->toDateTimeString(),
            'cpd_points_earned' => 20,
            'idempotency_key' => 'seed:cert:admin:loto',
        ]);
    }
}
