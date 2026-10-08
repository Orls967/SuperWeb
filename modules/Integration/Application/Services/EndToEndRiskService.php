<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EndToEndRiskService (Fase 253)
 *
 * Implements:
 *  - 253.1 Risk process unification (identify, assess, treat, monitor, incident bridge, board reporting, postmortem learning)
 *  - 253.2 Cross-line aggregate risk & tail risk (VaR 99%) capital buffer modeling
 *  - 253.3 Assurance coverage map (internal audit, external audit, compliance testing)
 *  - 253.5 Edge case: Material process without assurance flags gap & demands remediation plan
 *  - 253.6 Automatic event-driven incident bridge to risk register
 *  - 253.7 Independent assurance sampler: self-review by process owners strictly banned
 */
class EndToEndRiskService
{
    /**
     * Register unified risk in central register (253.1).
     */
    public function registerUnifiedRisk(
        string $riskCode,
        string $businessLine,
        string $riskTitle,
        string $riskCategory,
        float $inherentScore,
        float $residualScore,
        float $kriMetricValue
    ): object {
        $code = strtoupper($riskCode);

        $id = DB::table('risk_unified_registers')->insertGetId([
            'risk_code' => $code,
            'business_line' => strtoupper($businessLine),
            'risk_title' => $riskTitle,
            'risk_category' => strtoupper($riskCategory),
            'inherent_score' => $inherentScore,
            'residual_score' => $residualScore,
            'kri_metric_value' => $kriMetricValue,
            'incident_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('risk_unified_registers')->find($id);
    }

    /**
     * Bridge incident into risk register automatically (253.1 & 253.6).
     */
    public function bridgeIncidentToRisk(int $riskId, string $incidentCode, float $impactUsd): object
    {
        $risk = DB::table('risk_unified_registers')->find($riskId);
        if (! $risk) {
            throw new InvalidArgumentException("Risk #{$riskId} not found.");
        }

        // Elevate residual score by 5 points per incident (capped at inherent score)
        $newResidual = min((float) $risk->inherent_score, (float) $risk->residual_score + 5.0);

        DB::table('risk_unified_registers')
            ->where('id', $riskId)
            ->update([
                'incident_count' => $risk->incident_count + 1,
                'residual_score' => $newResidual,
                'updated_at' => now(),
            ]);

        return (object) DB::table('risk_unified_registers')->find($riskId);
    }

    /**
     * Calculate cross-line aggregate portfolio risk & capital buffer (253.2 & 253.4).
     */
    public function calculateAggregatePortfolioRisk(
        string $portfolioCode,
        array $correlatedLines,
        float $rawSumVaRUsd,
        float $correlationFactor = 0.75
    ): object {
        // Diversification benefit formula
        $diversificationBenefitPct = round((1.0 - $correlationFactor) * 100.0, 2);
        $tailRiskVaR99 = round($rawSumVaRUsd * $correlationFactor, 2);
        // Required capital buffer = 1.25x of VaR 99
        $capitalBuffer = round($tailRiskVaR99 * 1.25, 2);

        $code = strtoupper($portfolioCode);

        $id = DB::table('risk_aggregate_correlations')->insertGetId([
            'portfolio_code' => $code,
            'correlated_lines_json' => json_encode($correlatedLines),
            'diversification_benefit_pct' => $diversificationBenefitPct,
            'tail_risk_var_99_usd' => $tailRiskVaR99,
            'capital_buffer_required_usd' => $capitalBuffer,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('risk_aggregate_correlations')->find($id);
    }

    /**
     * Map process assurance with self-review ban & coverage gap detection (253.3, 253.5, 253.7).
     */
    public function mapProcessAssurance(
        string $processCode,
        string $processName,
        bool $isMaterialProcess,
        string $processOwner,
        string $assuranceProvider,
        ?string $assuranceTesterId = null
    ): object {
        $ownerUpper = strtoupper($processOwner);
        $testerUpper = $assuranceTesterId ? strtoupper($assuranceTesterId) : null;

        // Anti-Self-Review Guard (253.7): Process owner cannot audit their own process!
        if ($testerUpper !== null && $testerUpper === $ownerUpper) {
            throw new InvalidArgumentException("Self-review prohibited: Assurance tester cannot be the process owner '{$ownerUpper}' (253.7).");
        }

        // Material gap check (253.5 Edge Case)
        $providerUpper = strtoupper($assuranceProvider);
        $hasGap = false;
        $remediationRequired = false;

        if ($isMaterialProcess && ($providerUpper === 'NONE' || empty($testerUpper))) {
            $hasGap = true;
            $remediationRequired = true;
        }

        $code = strtoupper($processCode);

        $id = DB::table('risk_assurance_coverage_maps')->insertGetId([
            'process_code' => $code,
            'process_name' => $processName,
            'is_material_process' => $isMaterialProcess,
            'process_owner' => $ownerUpper,
            'assurance_provider' => $providerUpper,
            'assurance_tester_id' => $testerUpper,
            'is_self_reviewed' => false,
            'has_coverage_gap' => $hasGap,
            'remediation_plan_required' => $remediationRequired,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('risk_assurance_coverage_maps')->find($id);
    }

    /**
     * End-to-End Risk Platform Audit (`risk:audit`) (253.4, 253.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Material processes with assurance gaps lacking remediation requirement
        $unremediatedGaps = DB::table('risk_assurance_coverage_maps')
            ->where('is_material_process', true)
            ->where('has_coverage_gap', true)
            ->where('remediation_plan_required', false)
            ->count();

        // Discrepancy 2: Self-reviewed assurance instances detected
        $selfReviewedAudits = DB::table('risk_assurance_coverage_maps')
            ->where('is_self_reviewed', true)
            ->count();

        // Discrepancy 3: Risks with incidents where residual exceeds inherent score
        $invertedRiskScores = DB::table('risk_unified_registers')
            ->whereRaw('residual_score > inherent_score')
            ->count();

        $discrepancies = $unremediatedGaps + $selfReviewedAudits + $invertedRiskScores;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_risks' => DB::table('risk_unified_registers')->count(),
            'total_portfolios' => DB::table('risk_aggregate_correlations')->count(),
            'total_processes_mapped' => DB::table('risk_assurance_coverage_maps')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
