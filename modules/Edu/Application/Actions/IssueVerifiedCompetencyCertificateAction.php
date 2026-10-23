<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class IssueVerifiedCompetencyCertificateAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): EduCertificate
    {
        $enrollmentId = (string) $params['enrollment_id'];
        $enrollment = EduEnrollment::findOrFail($enrollmentId);

        if ($enrollment->status === 'CANCELLED') {
            throw new RuntimeException('Cannot issue certificate for cancelled enrollment.');
        }

        return DB::transaction(function () use ($params, $enrollment) {
            $studentId = $enrollment->student_party_id;
            $cohort = EduCohort::find($enrollment->cohort_id);
            $programId = (string) ($params['program_id'] ?? ($cohort ? $cohort->program_id : 'PRG-DEFAULT'));
            $competencyCode = (string) $params['skill_competency_code'];
            $expiresAt = (string) ($params['expires_at'] ?? Carbon::now()->addYears(2)->toDateString());

            $certNum = 'CERT-'.strtoupper(substr(hash('sha256', $enrollment->id.':'.$competencyCode), 0, 10));
            $payload = $certNum.'|'.$studentId.'|'.$competencyCode.'|'.$expiresAt;
            $hash = hash('sha256', $payload);
            $qrUrl = "https://verify.ecosystem.id/cert/{$hash}";

            $cert = EduCertificate::create([
                'id' => (string) Str::uuid(),
                'certificate_number' => $certNum,
                'enrollment_id' => $enrollment->id,
                'student_party_id' => $studentId,
                'program_id' => $programId,
                'skill_competency_code' => $competencyCode,
                'certificate_hash' => $hash,
                'qr_verification_url' => $qrUrl,
                'issued_at' => Carbon::now()->toDateString(),
                'expires_at' => $expiresAt,
                'cpd_points_earned' => (int) ($params['cpd_points_earned'] ?? 10),
                'status' => 'ACTIVE',
            ]);

            $enrollment->update(['status' => 'GRADUATED']);

            $this->audit(
                action: 'edu_certificate_issued',
                auditable: $cert,
                newValues: ['certificate_number' => $certNum, 'hash' => $hash],
                impactType: 'certification',
            );

            return $cert;
        });
    }
}
