<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class SettleCorporateAcademyBillingAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Settle B2B academy invoice for corporate cohort training.
     */
    public function execute(string $cohortId, string $corporateClientId): int
    {
        $cohort = EduCohort::findOrFail($cohortId);

        $enrollments = EduEnrollment::where('cohort_id', $cohort->id)
            ->where('corporate_client_id', $corporateClientId)
            ->get();

        if ($enrollments->isEmpty()) {
            throw new RuntimeException("No corporate enrollments found for client [{$corporateClientId}] in cohort [{$cohort->cohort_code}].");
        }

        $totalBilling = (int) $enrollments->sum('amount_paid_minor');

        return DB::transaction(function () use ($cohort, $corporateClientId, $totalBilling, $enrollments) {
            $idempotencyKey = hash('sha256', "CORP-EDU:{$cohort->cohort_code}:{$corporateClientId}:{$totalBilling}");

            if ($totalBilling > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'EDU_CORPORATE_SETTLEMENT',
                    description: "Corporate billing settlement for cohort {$cohort->cohort_code} and client {$corporateClientId}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('edu:tuition_receivable:IDR', $totalBilling)
                        ->credit('edu:corporate_revenue:IDR', $totalBilling)
                        ->toEntries('IDR'),
                    referenceType: 'EDU_CORP_SETTLEMENT',
                    referenceId: "{$cohort->cohort_code}-{$corporateClientId}",
                ));
            }

            $this->audit(
                action: 'edu_corporate_billing_settled',
                auditable: $cohort,
                newValues: [
                    'corporate_client_id' => $corporateClientId,
                    'total_amount' => $totalBilling,
                    'student_count' => $enrollments->count(),
                ],
                impactType: 'financial',
            );

            return $totalBilling;
        });
    }
}
