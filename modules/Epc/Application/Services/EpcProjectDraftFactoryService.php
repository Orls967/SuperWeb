<?php

declare(strict_types=1);

namespace Modules\Epc\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Epc\Contracts\ProjectDraftFactory;
use Modules\Epc\Domain\Models\EpcProject;

final class EpcProjectDraftFactoryService implements ProjectDraftFactory
{
    public function draftFromProposal(
        int $governanceProposalId,
        string $title,
        int $budgetIdr,
        string $idempotencyKey
    ): string {
        return DB::transaction(function () use ($governanceProposalId, $title, $budgetIdr, $idempotencyKey) {
            $projectCode = 'PRJ-GOV-'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 10));

            $existing = EpcProject::where('project_code', $projectCode)->first();
            if ($existing) {
                return (string) $existing->id;
            }

            $project = EpcProject::create([
                'project_code' => $projectCode,
                'project_name' => $title,
                'project_type' => 'STRATEGIC_EXPANSION',
                'client_entity_id' => 'GOVERNANCE-DAO-'.$governanceProposalId,
                'total_rab_budget_idr' => $budgetIdr,
                'target_physical_progress_pct' => 0.00,
                'actual_physical_progress_pct' => 0.00,
                'accumulated_cip_cost_idr' => 0,
                'capitalized_asset_value_idr' => 0,
                'start_date' => now()->toDateString(),
                'target_completion_date' => now()->addMonths(6)->toDateString(),
                'status' => 'draft_pending_approval',
            ]);

            return (string) $project->id;
        });
    }
}
