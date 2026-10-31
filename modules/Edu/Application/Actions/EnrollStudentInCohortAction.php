<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Edu\Domain\Exceptions\CohortCapacityExceededException;
use Modules\Edu\Domain\Exceptions\CorporateQuotaExceededException;
use Modules\Edu\Domain\Exceptions\IdempotencyConflictException;
use Modules\Edu\Domain\Exceptions\PrerequisiteNotMetException;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduCorporateQuota;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Shared\Application\BaseAction;

class EnrollStudentInCohortAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): EduEnrollment
    {
        $cohortId = (string) $params['cohort_id'];
        $idempotencyKey = isset($params['idempotency_key']) ? (string) $params['idempotency_key'] : null;

        $studentUserId = isset($params['student_user_id']) ? (int) $params['student_user_id'] : null;
        $studentPartyId = isset($params['student_party_id']) ? (string) $params['student_party_id'] : null;

        if ($studentUserId === null && $studentPartyId !== null && is_numeric($studentPartyId)) {
            $studentUserId = (int) $studentPartyId;
        }

        if ($studentPartyId === null) {
            $studentPartyId = $studentUserId !== null ? "student-{$studentUserId}" : 'student-unknown';
        }

        // Idempotency check
        if ($idempotencyKey !== null) {
            $existing = EduEnrollment::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $isSameCohort = $existing->cohort_id === $cohortId;
                $isSameStudent = $existing->student_party_id === $studentPartyId
                    || ($studentUserId !== null && $existing->student_user_id === $studentUserId);

                if ($isSameCohort && $isSameStudent) {
                    return $existing;
                }
                throw new IdempotencyConflictException("Conflicting enrollment payload for idempotency key: {$idempotencyKey}");
            }
        }

        return DB::transaction(function () use ($params, $cohortId, $studentUserId, $studentPartyId, $idempotencyKey) {
            $cohort = EduCohort::where('id', $cohortId)->lockForUpdate()->firstOrFail();
            $program = EduProgram::findOrFail($cohort->program_id);

            // 1. Capacity Enforcement
            if ($cohort->enrolled_count >= $cohort->max_capacity) {
                throw new CohortCapacityExceededException("Cohort {$cohort->cohort_code} has reached maximum capacity.");
            }

            // 2. Transitive Prerequisite DAG Enforcement (B0''.10)
            $transitivePrereqIds = app(AddProgramPrerequisiteAction::class)->getTransitivePrerequisites($program->id);
            if ($program->prerequisite_program_id && ! in_array($program->prerequisite_program_id, $transitivePrereqIds, true)) {
                $transitivePrereqIds[] = $program->prerequisite_program_id;
            }

            foreach ($transitivePrereqIds as $reqProgId) {
                $hasCert = EduCertificate::where('program_id', $reqProgId)
                    ->where(function ($q) use ($studentUserId, $studentPartyId) {
                        if ($studentUserId !== null) {
                            $q->where('student_user_id', $studentUserId);
                        }
                        if ($studentPartyId !== null) {
                            $q->orWhere('student_party_id', $studentPartyId);
                        }
                    })
                    ->where('status', 'ACTIVE')
                    ->exists();

                if (! $hasCert) {
                    throw new PrerequisiteNotMetException("Prerequisite program [{$reqProgId}] has not been completed with an active certificate.");
                }
            }

            // 3. Corporate Quota Handling
            $corporateClientId = $params['corporate_client_id'] ?? null;
            $corporateQuotaId = null;
            $tuition = (int) ($params['amount_paid_minor'] ?? $program->tuition_fee_minor);

            if ($corporateClientId !== null) {
                $quota = EduCorporateQuota::where('corporate_client_id', $corporateClientId)
                    ->where('program_id', $program->id)
                    ->where('status', 'ACTIVE')
                    ->lockForUpdate()
                    ->first();

                if (! $quota || $quota->quota_used >= $quota->quota_total) {
                    throw new CorporateQuotaExceededException("Corporate quota for client {$corporateClientId} on program {$program->program_code} is exceeded or unavailable.");
                }

                $quota->increment('quota_used');
                $corporateQuotaId = $quota->id;
                $tuition = $quota->unit_price_minor;
            }

            // 4. Deterministic Enrollment Code Generation (PR3/X4 without microtime)
            $codeSeed = $cohort->id.':'.$studentPartyId.':'.($idempotencyKey ?? 'default');
            $enrollmentCode = $params['enrollment_code'] ?? ('ENR-'.strtoupper(substr(hash('sha256', $codeSeed), 0, 10)));

            $enrollment = EduEnrollment::create([
                'id' => (string) Str::uuid(),
                'enrollment_code' => $enrollmentCode,
                'student_user_id' => $studentUserId,
                'student_party_id' => $studentPartyId,
                'cohort_id' => $cohort->id,
                'corporate_quota_id' => $corporateQuotaId,
                'corporate_client_id' => $corporateClientId,
                'amount_paid_minor' => $tuition,
                'sessions_attended' => 0,
                'total_sessions' => $program->total_sessions,
                'refund_amount_minor' => 0,
                'status' => 'ENROLLED',
                'idempotency_key' => $idempotencyKey,
            ]);

            $cohort->increment('enrolled_count');

            // 5. Individual Tuition Payment Ledger Posting
            if (empty($corporateClientId) && $tuition > 0) {
                $postingKey = 'edu:pay:'.$enrollment->enrollment_code;
                $this->ledgerService->post(new PostingDTO(
                    type: 'EDU_TUITION_PAYMENT',
                    description: "Tuition payment for enrollment {$enrollment->enrollment_code}",
                    idempotencyKey: $postingKey,
                    entries: Posting::lines()
                        ->debit('edu:tuition_receivable:IDR', $tuition)
                        ->credit('edu:tuition_revenue:IDR', $tuition)
                        ->toEntries('IDR'),
                    referenceType: 'EDU_ENROLLMENT',
                    referenceId: $enrollment->enrollment_code,
                ));
            }

            $this->audit(
                action: 'edu_student_enrolled',
                auditable: $enrollment,
                newValues: ['cohort_code' => $cohort->cohort_code, 'amount_paid' => $tuition],
                impactType: 'education',
            );

            return $enrollment;
        });
    }
}
