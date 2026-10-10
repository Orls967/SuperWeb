<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * OperationsExcellenceService (Fase 276)
 *
 * Implements:
 *  - 276.1 Continuous Improvement (CI) DMAIC project lifecycle with ledger-verified financial savings
 *  - 276.2 Operational KPI trees with objective status colors (Green/Yellow/Red)
 *  - 276.3 Standard work library & site adoption tracking
 *  - 276.5 Edge case: Site standardization failures require formal variance justification & approval (no secret deviations)
 *  - 276.6 Finance verification mandatory: unverified savings claims are strictly rejected/unrecognized
 *  - 276.7 Anti-gaming counter-metric: green operational KPIs with elevated customer complaint rates flagged for gaming
 */
class OperationsExcellenceService
{
    /**
     * Create DMAIC improvement project (276.1).
     */
    public function createDmaicProject(
        string $projectCode,
        string $title,
        string $ownerLeadId,
        float $baselineMetric,
        float $targetMetric,
        float $claimedSavingsUsd
    ): object {
        $code = strtoupper($projectCode);

        $id = DB::table('operations_dmaic_projects')->insertGetId([
            'project_code' => $code,
            'project_title' => $title,
            'dmaic_stage' => 'DEFINE',
            'owner_lead_id' => strtoupper($ownerLeadId),
            'baseline_metric_value' => $baselineMetric,
            'target_metric_value' => $targetMetric,
            'claimed_financial_savings_usd' => $claimedSavingsUsd,
            'verified_financial_savings_usd' => 0.0,
            'is_finance_verified' => false,
            'finance_auditor_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('operations_dmaic_projects')->find($id);
    }

    /**
     * Finance verifies CI financial savings; claims without ledger audit are rejected (276.4 & 276.6).
     */
    public function verifyCiSavings(
        string $projectCode,
        string $financeAuditorId,
        float $ledgerVerifiedSavingsUsd
    ): object {
        $code = strtoupper($projectCode);
        $project = DB::table('operations_dmaic_projects')->where('project_code', $code)->first();
        if (! $project) {
            throw new InvalidArgumentException("Project '{$projectCode}' not found.");
        }

        // Must have verified savings > 0 to recognize (276.6)
        if ($ledgerVerifiedSavingsUsd <= 0.0) {
            throw new InvalidArgumentException('Finance verification failed: Claimed savings without audited ledger proof cannot be recognized (276.6).');
        }

        DB::table('operations_dmaic_projects')
            ->where('project_code', $code)
            ->update([
                'dmaic_stage' => 'CONTROL', // Reached completion
                'verified_financial_savings_usd' => $ledgerVerifiedSavingsUsd,
                'is_finance_verified' => true,
                'finance_auditor_id' => strtoupper($financeAuditorId),
                'updated_at' => now(),
            ]);

        return (object) DB::table('operations_dmaic_projects')->where('project_code', $code)->first();
    }

    /**
     * Record operational KPI and evaluate anti-gaming counter-metric (276.2 & 276.7).
     */
    public function recordOperationalKpi(
        string $kpiCode,
        string $domainLine,
        string $kpiName,
        float $currentValue,
        float $targetValue,
        float $customerComplaintRatePct = 0.0
    ): object {
        $code = strtoupper($kpiCode);
        $ratio = $currentValue / max(0.01, $targetValue);

        // Status color determination (276.2)
        $color = ($ratio >= 1.0) ? 'GREEN' : (($ratio >= 0.8) ? 'YELLOW' : 'RED');

        // Anti-gaming counter-metric (276.7): KPI green but customer complaints > 5% indicates metric gaming
        $isGaming = ($color === 'GREEN' && $customerComplaintRatePct > 5.0);

        DB::table('operations_kpi_trees')->updateOrInsert(
            ['kpi_code' => $code],
            [
                'domain_line' => strtoupper($domainLine),
                'kpi_name' => $kpiName,
                'current_value' => $currentValue,
                'target_value' => $targetValue,
                'status_color' => $color,
                'customer_complaint_rate_pct' => $customerComplaintRatePct,
                'is_gaming_flagged' => $isGaming,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('operations_kpi_trees')->where('kpi_code', $code)->first();
    }

    /**
     * Register standard work SOP and track site adoption (276.3).
     */
    public function registerStandardWork(string $standardCode, string $title, int $adoptingSites = 0): object
    {
        $code = strtoupper($standardCode);

        $id = DB::table('operations_standard_work_library')->insertGetId([
            'standard_code' => $code,
            'title' => $title,
            'playbook_version' => 'V1.0',
            'adopting_sites_count' => $adoptingSites,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('operations_standard_work_library')->find($id);
    }

    /**
     * Request site standard variance with justification and approval (276.5 Edge Case).
     */
    public function requestSiteVariance(
        string $standardCode,
        string $siteCode,
        string $justification,
        ?string $approvedByOpsHead = null
    ): object {
        if (empty($justification)) {
            throw new InvalidArgumentException('Standardization variance requires mandatory technical/geographical justification (276.5).');
        }

        $varianceCode = 'VAR-'.strtoupper(Str::random(8));
        $isApproved = ! empty($approvedByOpsHead);

        $id = DB::table('operations_site_standard_variances')->insertGetId([
            'variance_code' => $varianceCode,
            'standard_code' => strtoupper($standardCode),
            'site_code' => strtoupper($siteCode),
            'variance_justification' => $justification,
            'is_variance_approved' => $isApproved,
            'approved_by_ops_head' => $approvedByOpsHead ? strtoupper($approvedByOpsHead) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('operations_site_standard_variances')->find($id);
    }

    /**
     * Operations Excellence Platform Audit (`quality:audit`) (276.4, 276.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: DMAIC projects completed without finance verification
        $unverifiedCompletedDmaic = DB::table('operations_dmaic_projects')
            ->where('dmaic_stage', 'CONTROL')
            ->where('is_finance_verified', false)
            ->count();

        // Discrepancy 2: Site standard variances without ops head approval
        $unapprovedVariances = DB::table('operations_site_standard_variances')
            ->where('is_variance_approved', false)
            ->count();

        // Discrepancy 3: Green KPIs flagged for gaming without review
        $unaddressedGamingKpis = DB::table('operations_kpi_trees')
            ->where('is_gaming_flagged', true)
            ->count();

        $discrepancies = $unverifiedCompletedDmaic + $unapprovedVariances + $unaddressedGamingKpis;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_dmaic_projects' => DB::table('operations_dmaic_projects')->count(),
            'total_kpis' => DB::table('operations_kpi_trees')->count(),
            'total_standards' => DB::table('operations_standard_work_library')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
