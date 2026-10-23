<?php

declare(strict_types=1);

namespace Modules\Epc\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Epc\Domain\Models\EpcProject;

class IssueProjectBast1WithRetentionHoldAction
{
    /**
     * @param array{
     *     project_id: string,
     *     bast_number?: string,
     *     warranty_period_days?: int,
     *     signoff_representative_client?: string,
     *     signoff_representative_contractor?: string,
     *     notes?: string
     * } $data
     * @return array{
     *     project_id: string,
     *     bast_number: string,
     *     bast_type: string,
     *     accumulated_cip_cost_idr: int,
     *     retention_warranty_held_idr: int,
     *     warranty_period_days: int,
     *     warranty_expiry_date: string,
     *     status: string
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $project = EpcProject::where('id', $data['project_id'])->lockForUpdate()->firstOrFail();

            if ((float) $project->actual_physical_progress_pct < 95.0) {
                throw new InvalidArgumentException("Project physical progress ({$project->actual_physical_progress_pct}%) is below the minimum 95% threshold required for BAST-1 issuance.");
            }

            $bastNumber = $data['bast_number'] ?? 'BAST-1-'.strtoupper(Str::random(6));
            $warrantyDays = (int) ($data['warranty_period_days'] ?? 180);
            $warrantyExpiryDate = Carbon::now()->addDays($warrantyDays)->toDateString();

            // Accumulated retention is approximately 5% of gross certified claims
            $totalCip = $project->accumulated_cip_cost_idr;
            $retentionHeld = (int) round($totalCip * 0.05);

            $project->update([
                'status' => 'bast_issued',
            ]);

            return [
                'project_id' => (string) $project->id,
                'bast_number' => $bastNumber,
                'bast_type' => 'BAST_1_PARTIAL',
                'accumulated_cip_cost_idr' => $totalCip,
                'retention_warranty_held_idr' => $retentionHeld,
                'warranty_period_days' => $warrantyDays,
                'warranty_expiry_date' => $warrantyExpiryDate,
                'status' => 'bast_issued',
            ];
        });
    }
}
