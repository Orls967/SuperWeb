<?php

declare(strict_types=1);

namespace Modules\Med\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Med\Domain\Models\MedProject;
use Modules\Med\Domain\Models\MedTalentContract;
use Modules\Shared\Application\BaseAction;

class DistributeBoxOfficeAndStreamingRoyaltyAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function execute(string $projectId, int $boxOfficeRevenueMinor): int
    {
        $project = MedProject::findOrFail($projectId);

        return DB::transaction(function () use ($project, $boxOfficeRevenueMinor) {
            $project->increment('box_office_revenue_minor', $boxOfficeRevenueMinor);

            $talentContracts = MedTalentContract::where('project_id', $project->id)->get();
            $totalRoyaltiesCalculated = 0;

            foreach ($talentContracts as $contract) {
                if ($contract->backend_percentage > 0) {
                    $royalty = (int) round(($boxOfficeRevenueMinor * $contract->backend_percentage) / 100);
                    $contract->increment('calculated_royalty_minor', $royalty);
                    $contract->update(['payout_status' => 'APPROVED']);
                    $totalRoyaltiesCalculated += $royalty;
                }
            }

            if ($totalRoyaltiesCalculated > 0) {
                $idempotencyKey = hash('sha256', "BOXOFFICE:{$project->project_code}:{$boxOfficeRevenueMinor}:{$totalRoyaltiesCalculated}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'MEDIA_BOX_OFFICE_DISTRIBUTE',
                    description: "Box office royalty distribution for {$project->project_code}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('med:talent_royalty_expense:IDR', $totalRoyaltiesCalculated)
                        ->credit('med:talent_royalty_payable:IDR', $totalRoyaltiesCalculated)
                        ->toEntries('IDR'),
                    referenceType: 'MED_PROJECT',
                    referenceId: $project->project_code,
                ));
            }

            $this->audit(
                action: 'med_box_office_distributed',
                auditable: $project,
                newValues: ['box_office_added' => $boxOfficeRevenueMinor, 'royalties_total' => $totalRoyaltiesCalculated],
                impactType: 'financial',
            );

            return $totalRoyaltiesCalculated;
        });
    }
}
