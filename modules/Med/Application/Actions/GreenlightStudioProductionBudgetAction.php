<?php

declare(strict_types=1);

namespace Modules\Med\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Med\Domain\Models\MedProject;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class GreenlightStudioProductionBudgetAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function execute(string $projectId, int $budgetLimitMinor): MedProject
    {
        $project = MedProject::findOrFail($projectId);

        if ($project->phase !== 'BRIEF') {
            throw new RuntimeException("Project [{$project->project_code}] is already past BRIEF phase.");
        }

        return DB::transaction(function () use ($project, $budgetLimitMinor) {
            $project->update([
                'budget_limit_minor' => $budgetLimitMinor,
                'phase' => 'PRE_PROD',
            ]);

            if ($budgetLimitMinor > 0) {
                $idempotencyKey = hash('sha256', "MED-BUDGET:{$project->project_code}:{$budgetLimitMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'MEDIA_STUDIO_BUDGET_RELEASE',
                    description: "Greenlight production budget for project {$project->project_code}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('med:production_budget_expense:IDR', $budgetLimitMinor)
                        ->credit('med:talent_royalty_payable:IDR', $budgetLimitMinor)
                        ->toEntries('IDR'),
                    referenceType: 'MED_PROJECT',
                    referenceId: $project->project_code,
                ));
            }

            $this->audit(
                action: 'med_project_greenlit',
                auditable: $project,
                newValues: ['budget_limit' => $budgetLimitMinor, 'phase' => 'PRE_PROD'],
                impactType: 'studio_production',
            );

            return $project->fresh();
        });
    }
}
