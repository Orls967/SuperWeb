<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

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
        $studentId = (string) $params['student_party_id'];
        $cohortId = (string) $params['cohort_id'];

        $cohort = EduCohort::findOrFail($cohortId);
        $program = EduProgram::findOrFail($cohort->program_id);

        if ($program->prerequisite_program_id) {
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
            $enrollmentCode = 'ENR-'.strtoupper(substr(hash('sha256', $cohort->id.':'.$studentId.':'.($params['enrollment_code'] ?? microtime())), 0, 10));

            $tuition = (int) ($params['amount_paid_minor'] ?? $program->tuition_fee_minor);

            $enrollment = EduEnrollment::create([
                'id' => (string) Str::uuid(),
                'enrollment_code' => $enrollmentCode,
                'student_party_id' => $studentId,
                'cohort_id' => $cohort->id,
                'corporate_client_id' => $params['corporate_client_id'] ?? null,
                'amount_paid_minor' => $tuition,
                'sessions_attended' => 0,
                'total_sessions' => $program->total_sessions,
                'refund_amount_minor' => 0,
                'status' => 'ENROLLED',
            ]);

            $cohort->increment('enrolled_count');

            if ($tuition > 0) {
                $idempotencyKey = hash('sha256', 'EDU-PAY-'.$enrollment->enrollment_code);
                $this->ledgerService->post(new PostingDTO(
                    type: 'EDU_TUITION_PAYMENT',
                    description: "Tuition payment for enrollment {$enrollment->enrollment_code}",
                    idempotencyKey: $idempotencyKey,
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
