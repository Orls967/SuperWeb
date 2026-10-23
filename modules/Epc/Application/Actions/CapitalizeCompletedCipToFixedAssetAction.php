<?php

declare(strict_types=1);

namespace Modules\Epc\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Epc\Domain\Models\EpcCipCapitalization;
use Modules\Epc\Domain\Models\EpcProject;
use Modules\Epc\Ledger\EpcTransactionTypes;

class CapitalizeCompletedCipToFixedAssetAction
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    /**
     * @param array{
     *     project_id: string,
     *     bast_number?: string,
     *     bast_type?: string,
     *     target_asset_category?: string,
     *     created_asset_id?: string,
     *     notes?: string
     * } $data
     */
    public function execute(array $data): EpcCipCapitalization
    {
        return DB::transaction(function () use ($data) {
            $project = EpcProject::where('id', $data['project_id'])->lockForUpdate()->firstOrFail();

            $cipCost = $project->accumulated_cip_cost_idr;
            if ($cipCost <= 0) {
                throw new InvalidArgumentException("Project {$project->project_code} has no accumulated CIP cost to capitalize.");
            }

            $bastNumber = $data['bast_number'] ?? 'BAST-FINAL-'.strtoupper(Str::random(6));
            $assetId = $data['created_asset_id'] ?? 'AST-BLD-'.strtoupper(Str::random(6));
            $targetCategory = $data['target_asset_category'] ?? 'BUILDING';

            $capitalization = EpcCipCapitalization::create([
                'project_id' => $project->id,
                'bast_number' => $bastNumber,
                'bast_type' => $data['bast_type'] ?? 'BAST_FINAL',
                'total_cip_cost_idr' => $cipCost,
                'target_asset_category' => $targetCategory,
                'created_asset_id' => $assetId,
                'capitalized_at' => now(),
                'notes' => $data['notes'] ?? 'Capitalization of completed EPC construction into corporate fixed asset register.',
            ]);

            $project->update([
                'accumulated_cip_cost_idr' => 0,
                'capitalized_asset_value_idr' => $project->capitalized_asset_value_idr + $cipCost,
                'status' => 'capitalized',
            ]);

            // Post ledger entry: Debit Fixed Asset Buildings, Credit Construction in Progress (CIP)
            $this->ledgerService->post(new PostingDTO(
                type: EpcTransactionTypes::EPC_CIP_CAPITALIZATION->value,
                description: "EPC CIP capitalization BAST {$capitalization->bast_number} for {$project->project_code}",
                idempotencyKey: "EPC-CAP-{$capitalization->bast_number}",
                entries: Posting::lines()
                    ->debit('asset:fixed:capitalized_buildings:IDR', $cipCost)
                    ->credit('asset:epc:construction_in_progress:IDR', $cipCost)
                    ->toEntries('IDR'),
                referenceType: 'EPC_CAPITALIZATION',
                referenceId: (string) $capitalization->id,
            ));

            return $capitalization;
        });
    }
}
