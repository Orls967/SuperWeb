<?php

namespace Modules\Epc\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Epc\Domain\Models\EpcCipCapitalization;
use Modules\Epc\Domain\Models\EpcProgressCertificate;
use Modules\Epc\Domain\Models\EpcProject;
use Modules\Epc\Domain\Models\EpcWbsNode;

class EpcService
{
    public function createProject(array $data): EpcProject
    {
        return DB::transaction(function () use ($data) {
            return EpcProject::create([
                'project_code' => $data['project_code'] ?? 'EPC-'.strtoupper(Str::random(6)),
                'project_name' => $data['project_name'],
                'project_type' => $data['project_type'] ?? 'mall_extension',
                'client_entity_id' => $data['client_entity_id'],
                'total_rab_budget_idr' => (int) $data['total_rab_budget_idr'],
                'target_physical_progress_pct' => 0.00,
                'actual_physical_progress_pct' => 0.00,
                'accumulated_cip_cost_idr' => 0,
                'capitalized_asset_value_idr' => 0,
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'target_completion_date' => $data['target_completion_date'] ?? now()->addYear()->toDateString(),
                'status' => 'in_progress',
            ]);
        });
    }

    public function addWbsNode(string $projectId, array $data): EpcWbsNode
    {
        return DB::transaction(function () use ($projectId, $data) {
            $project = EpcProject::where('id', $projectId)->lockForUpdate()->firstOrFail();

            return EpcWbsNode::create([
                'project_id' => $project->id,
                'wbs_code' => $data['wbs_code'],
                'task_name' => $data['task_name'],
                'work_package' => $data['work_package'] ?? 'civil_structure',
                'weight_percentage' => (float) $data['weight_percentage'],
                'budget_allocation_idr' => (int) $data['budget_allocation_idr'],
                'actual_cost_incurred_idr' => (int) ($data['actual_cost_incurred_idr'] ?? 0),
                'completion_percentage' => (float) ($data['completion_percentage'] ?? 0.00),
                'status' => 'pending',
            ]);
        });
    }

    public function issueMonthlyCertificate(string $projectId, array $data): EpcProgressCertificate
    {
        return DB::transaction(function () use ($projectId, $data) {
            $project = EpcProject::where('id', $projectId)->lockForUpdate()->firstOrFail();

            $cumulativePct = (float) $data['certified_cumulative_progress_pct'];
            $incrementalPct = (float) $data['certified_incremental_progress_pct'];

            // Gross claim based on project budget and incremental progress
            $grossClaim = (int) round(($project->total_rab_budget_idr * $incrementalPct) / 100);
            $retentionDeduction = (int) round($grossClaim * 0.05); // 5% retention
            $netPayable = $grossClaim - $retentionDeduction;

            $certificate = EpcProgressCertificate::create([
                'project_id' => $project->id,
                'certificate_number' => $data['certificate_number'] ?? 'MC-'.strtoupper(Str::random(6)),
                'period_month' => (int) ($data['period_month'] ?? date('n')),
                'period_year' => (int) ($data['period_year'] ?? date('Y')),
                'certified_cumulative_progress_pct' => $cumulativePct,
                'certified_incremental_progress_pct' => $incrementalPct,
                'gross_claim_amount_idr' => $grossClaim,
                'retention_deduction_idr' => $retentionDeduction,
                'net_payable_amount_idr' => $netPayable,
                'supervising_consultant_name' => $data['supervising_consultant_name'] ?? 'PT Virama Karya Konsultan',
                'certified_at' => $data['certified_at'] ?? now(),
                'status' => 'approved',
            ]);

            // Update project actual progress and accumulated CIP
            $project->update([
                'actual_physical_progress_pct' => $cumulativePct,
                'accumulated_cip_cost_idr' => $project->accumulated_cip_cost_idr + $grossClaim,
            ]);

            return $certificate;
        });
    }

    public function capitalizeCipToAsset(string $projectId, array $data): EpcCipCapitalization
    {
        return DB::transaction(function () use ($projectId, $data) {
            $project = EpcProject::where('id', $projectId)->lockForUpdate()->firstOrFail();

            $cipCost = $project->accumulated_cip_cost_idr;
            if ($cipCost <= 0) {
                throw new \InvalidArgumentException('No CIP cost accumulated to capitalize.');
            }

            $capitalization = EpcCipCapitalization::create([
                'project_id' => $project->id,
                'bast_number' => $data['bast_number'] ?? 'BAST-FINAL-'.strtoupper(Str::random(6)),
                'bast_type' => $data['bast_type'] ?? 'BAST_FINAL',
                'total_cip_cost_idr' => $cipCost,
                'target_asset_category' => $data['target_asset_category'] ?? 'BUILDING',
                'created_asset_id' => $data['created_asset_id'] ?? 'AST-'.strtoupper(Str::random(8)),
                'capitalized_at' => now(),
                'notes' => $data['notes'] ?? 'Final handover from Construction In Progress to Fixed Asset.',
            ]);

            $project->update([
                'accumulated_cip_cost_idr' => 0,
                'capitalized_asset_value_idr' => $project->capitalized_asset_value_idr + $cipCost,
                'status' => 'capitalized',
            ]);

            return $capitalization;
        });
    }

    public function auditEpc(): array
    {
        $discrepancies = [];

        // Check 1: Progress certificate gross claims vs project accumulated CIP + capitalized value
        $projects = EpcProject::with('progressCertificates', 'capitalizations')->get();
        foreach ($projects as $project) {
            $sumGrossClaims = (int) $project->progressCertificates->sum('gross_claim_amount_idr');
            $totalRecorded = $project->accumulated_cip_cost_idr + $project->capitalized_asset_value_idr;

            if ($sumGrossClaims !== $totalRecorded) {
                $discrepancies[] = "Project {$project->project_code} CIP discrepancy: claims sum {$sumGrossClaims}, recorded CIP+Capitalized {$totalRecorded}";
            }

            // Check 2: Progress cannot exceed 100%
            if ((float) $project->actual_physical_progress_pct > 100.00) {
                $discrepancies[] = "Project {$project->project_code} actual progress exceeds 100%: {$project->actual_physical_progress_pct}%";
            }
        }

        return [
            'status' => count($discrepancies) === 0 ? 'HEALTHY' : 'DISCREPANCY',
            'projects_count' => $projects->count(),
            'certificates_count' => EpcProgressCertificate::count(),
            'capitalizations_count' => EpcCipCapitalization::count(),
            'total_active_cip_idr' => (int) $projects->sum('accumulated_cip_cost_idr'),
            'total_capitalized_idr' => (int) $projects->sum('capitalized_asset_value_idr'),
            'discrepancies' => $discrepancies,
        ];
    }
}
