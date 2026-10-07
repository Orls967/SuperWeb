<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduProgram;
use RuntimeException;

class AcademyAndCertificationService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function createProgram(array $params): EduProgram
    {
        return EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => $params['program_code'] ?? 'PRG-'.strtoupper(Str::random(6)),
            'title' => $params['title'],
            'industry_sector' => $params['industry_sector'],
            'prerequisite_program_id' => $params['prerequisite_program_id'] ?? null,
            'total_sessions' => (int) ($params['total_sessions'] ?? 10),
            'tuition_fee_minor' => (int) $params['tuition_fee_minor'],
            'status' => 'ACTIVE',
        ]);
    }

    public function openCohort(array $params): EduCohort
    {
        return EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => $params['cohort_code'] ?? 'CHT-'.strtoupper(Str::random(6)),
            'program_id' => $params['program_id'],
            'instructor_party_id' => $params['instructor_party_id'],
            'start_date' => $params['start_date'],
            'end_date' => $params['end_date'],
            'max_capacity' => (int) ($params['max_capacity'] ?? 30),
            'enrolled_count' => 0,
            'status' => 'OPEN',
        ]);
    }

    /**
     * 135.3 Enrollment & prerequisite enforcement.
     * Test (a): prerequisite tak terpenuhi -> enrollment ditolak.
     */
    public function enrollStudent(array $params): EduEnrollment
    {
        $studentId = $params['student_party_id'];
        $cohortId = $params['cohort_id'];

        $cohort = EduCohort::findOrFail($cohortId);
        $program = EduProgram::findOrFail($cohort->program_id);

        if ($program->prerequisite_program_id) {
            // Check if student has graduated/passed prerequisite
            $prereqPassed = EduCertificate::where('student_party_id', $studentId)
                ->where('program_id', $program->prerequisite_program_id)
                ->where('status', 'ACTIVE')
                ->exists();

            if (! $prereqPassed) {
                throw new RuntimeException("Prerequisite program {$program->prerequisite_program_id} has not been completed.");
            }
        }

        if ($cohort->enrolled_count >= $cohort->max_capacity) {
            throw new RuntimeException("Cohort {$cohort->cohort_code} has reached maximum capacity.");
        }

        return DB::transaction(function () use ($params, $cohort, $program, $studentId) {
            $enrollment = EduEnrollment::create([
                'id' => (string) Str::uuid(),
                'enrollment_code' => 'ENR-'.strtoupper(Str::random(8)),
                'student_party_id' => $studentId,
                'cohort_id' => $cohort->id,
                'corporate_client_id' => $params['corporate_client_id'] ?? null,
                'amount_paid_minor' => (int) $params['amount_paid_minor'],
                'sessions_attended' => 0,
                'total_sessions' => $program->total_sessions,
                'refund_amount_minor' => 0,
                'status' => 'ENROLLED',
            ]);

            $cohort->enrolled_count += 1;
            $cohort->save();

            // Settle tuition in ledger
            $tuition = (int) $params['amount_paid_minor'];
            if ($tuition > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'EDU_TUITION_PAYMENT',
                    description: "Tuition payment for enrollment {$enrollment->enrollment_code}",
                    idempotencyKey: 'EDU-PAY-'.$enrollment->enrollment_code,
                    entries: [
                        PostingEntryDTO::forCode('edu:tuition_receivable:IDR', 'IDR', $tuition),
                        PostingEntryDTO::forCode('edu:tuition_revenue:IDR', 'IDR', -$tuition),
                    ],
                    referenceType: 'EDU_ENROLLMENT',
                    referenceId: $enrollment->enrollment_code,
                ));
            }

            return $enrollment;
        });
    }

    /**
     * 135.3 Pro-rata refund for dropouts.
     * Test (c): refund pro-rata = fraction * remaining sessions.
     */
    public function processProRataRefund(string $enrollmentId, int $sessionsAttended): EduEnrollment
    {
        $enrollment = EduEnrollment::findOrFail($enrollmentId);

        if ($sessionsAttended >= $enrollment->total_sessions) {
            throw new RuntimeException('Cannot refund after all sessions attended.');
        }

        $remainingSessions = $enrollment->total_sessions - $sessionsAttended;
        $pricePerSession = $enrollment->amount_paid_minor / (float) $enrollment->total_sessions;
        $refundMinor = (int) round($remainingSessions * $pricePerSession);

        return DB::transaction(function () use ($enrollment, $sessionsAttended, $refundMinor) {
            $enrollment->sessions_attended = $sessionsAttended;
            $enrollment->refund_amount_minor = $refundMinor;
            $enrollment->status = 'CANCELLED';
            $enrollment->save();

            // Reverse ledger entries for refund
            if ($refundMinor > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'EDU_TUITION_PRO_RATA_REFUND',
                    description: "Pro-rata refund for dropped enrollment {$enrollment->enrollment_code}",
                    idempotencyKey: 'EDU-REFUND-'.$enrollment->enrollment_code,
                    entries: [
                        PostingEntryDTO::forCode('edu:tuition_revenue:IDR', 'IDR', $refundMinor),
                        PostingEntryDTO::forCode('edu:tuition_receivable:IDR', 'IDR', -$refundMinor),
                    ],
                    referenceType: 'EDU_ENROLLMENT',
                    referenceId: $enrollment->enrollment_code,
                ));
            }

            return $enrollment;
        });
    }

    /**
     * 135.4 Hash-chain verifiable certificates & QR proof.
     * Test (b): sertifikat hash valid & QR terverifikasi.
     */
    public function issueCertificate(array $params): EduCertificate
    {
        $certNum = 'CERT-'.strtoupper(Str::random(10));
        $salt = (string) Str::uuid();
        $payload = $certNum.'|'.$params['student_party_id'].'|'.$params['skill_competency_code'].'|'.$params['expires_at'].'|'.$salt;
        $hash = hash('sha256', $payload);

        $qrUrl = "https://verify.ecosystem.id/cert/{$hash}";

        return EduCertificate::create([
            'id' => (string) Str::uuid(),
            'certificate_number' => $certNum,
            'enrollment_id' => $params['enrollment_id'],
            'student_party_id' => $params['student_party_id'],
            'program_id' => $params['program_id'],
            'skill_competency_code' => $params['skill_competency_code'],
            'certificate_hash' => $hash,
            'qr_verification_url' => $qrUrl,
            'issued_at' => Carbon::now()->toDateString(),
            'expires_at' => $params['expires_at'],
            'cpd_points_earned' => (int) ($params['cpd_points_earned'] ?? 10),
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * 135.4 & 135.5 Validation for critical role assignment.
     * Test (d): sertifikat expired memblokir penugasan role kritis.
     */
    public function verifyCompetencyForAssignment(string $studentId, string $competencyCode): bool
    {
        $cert = EduCertificate::where('student_party_id', $studentId)
            ->where('skill_competency_code', $competencyCode)
            ->where('status', 'ACTIVE')
            ->first();

        if (! $cert) {
            return false;
        }

        // Check expiration
        if (Carbon::parse($cert->expires_at)->isPast()) {
            $cert->status = 'EXPIRED';
            $cert->save();

            return false;
        }

        return true;
    }
}
