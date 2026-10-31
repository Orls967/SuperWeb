<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\ClockInterface;
use Modules\Edu\Domain\Exceptions\ExamNotPassedException;
use Modules\Edu\Domain\Exceptions\IdempotencyConflictException;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCertificateSequence;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduExamAttempt;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Edu\Domain\Services\CertificateQrSigner;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\Numerics\CanonicalHashChain;
use RuntimeException;

class IssueVerifiedCompetencyCertificateAction extends BaseAction
{
    public function __construct(
        protected ClockInterface $clock
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): EduCertificate
    {
        $enrollmentId = (string) $params['enrollment_id'];
        $idempotencyKey = isset($params['idempotency_key']) ? (string) $params['idempotency_key'] : null;

        $enrollment = EduEnrollment::findOrFail($enrollmentId);
        if ($enrollment->status === 'CANCELLED') {
            throw new RuntimeException('Cannot issue certificate for cancelled enrollment.');
        }

        // Validate exam attempt if passed
        $attempt = null;
        if (isset($params['exam_attempt_id'])) {
            $attempt = EduExamAttempt::findOrFail((string) $params['exam_attempt_id']);
            if (! $attempt->is_passed) {
                throw new ExamNotPassedException("Exam attempt [{$attempt->attempt_code}] was not passed. Score: {$attempt->score}, Passing: {$attempt->passing_score}.");
            }
        }

        // Idempotency check
        if ($idempotencyKey !== null) {
            $existing = EduCertificate::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $isSameEnrollment = $existing->enrollment_id === $enrollmentId;
                $isSameAttempt = $attempt === null || $existing->exam_attempt_id === $attempt->id;

                if ($isSameEnrollment && $isSameAttempt) {
                    return $existing;
                }
                throw new IdempotencyConflictException("Conflicting certificate issuance payload for idempotency key: {$idempotencyKey}");
            }
        }

        return DB::transaction(function () use ($params, $enrollment, $attempt, $idempotencyKey) {
            $studentId = $enrollment->student_party_id;
            $studentUserId = $enrollment->student_user_id;

            $cohort = EduCohort::find($enrollment->cohort_id);
            $programId = (string) ($params['program_id'] ?? ($cohort ? $cohort->program_id : 'PRG-DEFAULT'));
            $program = EduProgram::findOrFail($programId);

            $competencyCode = (string) ($params['skill_competency_code'] ?? $program->program_code);
            $issuedAt = isset($params['issued_at'])
                ? Carbon::parse($params['issued_at'])
                : Carbon::parse($this->clock->{'now'}()->toDateTimeString());
            $expiresAt = isset($params['expires_at'])
                ? Carbon::parse($params['expires_at'])
                : $issuedAt->copy()->addMonths($program->validity_months ?: 24);

            $year = $issuedAt->year;

            // 1. Gapless Numbering Lock per Program per Year Period (S4/PR2)
            $seqRecord = EduCertificateSequence::firstOrCreate(
                ['program_id' => $program->id, 'year_period' => $year],
                ['id' => (string) Str::uuid(), 'last_sequence' => 0]
            );

            $seqRecord = EduCertificateSequence::where('id', $seqRecord->id)->lockForUpdate()->first();
            $nextSeq = $seqRecord->last_sequence + 1;
            $seqRecord->update(['last_sequence' => $nextSeq]);

            $certNum = sprintf('CERT-%d-%s-%06d', $year, $program->program_code, $nextSeq);

            // 2. Canonical Hash-Chain Generation (B0''.8 CanonicalHashChain)
            $lastCert = EduCertificate::where('program_id', $program->id)
                ->orderBy('certificate_number', 'desc')
                ->lockForUpdate()
                ->first();

            $prevHash = $lastCert ? $lastCert->certificate_hash : CanonicalHashChain::GENESIS_PREV_HASH;

            $payload = [
                'certificate_number' => $certNum,
                'program_id' => $program->id,
                'student_identity' => (string) ($studentUserId ?? $studentId),
                'exam_attempt_id' => $attempt ? $attempt->id : 'none',
                'skill_competency_code' => $competencyCode,
                'issued_at' => $issuedAt->toDateString(),
                'expires_at' => $expiresAt->toDateString(),
            ];

            $certHash = CanonicalHashChain::computeHash($prevHash, $payload);

            // 3. QR Token Signature
            $certUuid = (string) ($params['id'] ?? Str::uuid());
            $qrSigner = app(CertificateQrSigner::class);
            $qrToken = $qrSigner->generateToken($certUuid, 'v1');
            $qrUrl = url("/academy/certificates/verify?token={$qrToken}");

            $cert = EduCertificate::create([
                'id' => $certUuid,
                'certificate_number' => $certNum,
                'enrollment_id' => $enrollment->id,
                'student_user_id' => $studentUserId,
                'student_party_id' => $studentId,
                'program_id' => $program->id,
                'exam_attempt_id' => $attempt ? $attempt->id : null,
                'skill_competency_code' => $competencyCode,
                'certificate_hash' => $certHash,
                'prev_hash' => $prevHash,
                'qr_token' => $qrToken,
                'qr_verification_url' => $qrUrl,
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
                'cpd_points_earned' => (int) ($params['cpd_points_earned'] ?? 10),
                'status' => 'ACTIVE',
                'idempotency_key' => $idempotencyKey,
            ]);

            $enrollment->update(['status' => 'GRADUATED']);

            $this->audit(
                action: 'edu_certificate_issued',
                auditable: $cert,
                newValues: ['certificate_number' => $certNum, 'skill_code' => $competencyCode],
                impactType: 'certification',
            );

            return $cert;
        });
    }
}
