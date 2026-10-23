<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Edu\Domain\Models\EduGrantMilestone;
use Modules\Edu\Domain\Models\EduResearchGrant;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class DisburseResearchGrantMilestoneAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function execute(string $milestoneId): EduGrantMilestone
    {
        $milestone = EduGrantMilestone::findOrFail($milestoneId);
        $grant = EduResearchGrant::findOrFail($milestone->grant_uuid);

        if ($milestone->status === 'DISBURSED') {
            throw new RuntimeException("Milestone [{$milestone->milestone_code}] has already been disbursed.");
        }

        if ($grant->status !== 'ACTIVE') {
            throw new RuntimeException("Grant [{$grant->grant_code}] is not active.");
        }

        return DB::transaction(function () use ($milestone, $grant) {
            $amount = $milestone->disbursement_amount_minor;

            $milestone->update([
                'status' => 'DISBURSED',
                'disbursed_at' => Carbon::now(),
            ]);

            $grant->increment('disbursed_grant_minor', $amount);

            if ($amount > 0) {
                $idempotencyKey = hash('sha256', 'EDU-GRANT-'.$milestone->milestone_code);
                $this->ledgerService->post(new PostingDTO(
                    type: 'EDU_RESEARCH_GRANT_DISBURSE',
                    description: "Disbursement for research grant milestone {$milestone->milestone_code}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('edu:research_grant_expense:IDR', $amount)
                        ->credit('edu:escrow_liability:IDR', $amount)
                        ->toEntries('IDR'),
                    referenceType: 'EDU_GRANT_MILESTONE',
                    referenceId: $milestone->milestone_code,
                ));
            }

            $this->audit(
                action: 'edu_grant_milestone_disbursed',
                auditable: $milestone,
                newValues: ['amount' => $amount, 'grant_code' => $grant->grant_code],
                impactType: 'financial',
            );

            return $milestone->fresh();
        });
    }
}
