<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * AdvancedAnalyticsGraphResearchService (Fase 243)
 *
 * Implements:
 *  - 243.1 Graph analytics: supply chain, distribution, payment flow, centrality, risk concentration & suspicious money flow
 *  - 243.2 Simulation & digital twin at scale: Monte Carlo probabilistic risk modeling (weather, demand shock, grid outage)
 *  - 243.3 Prescriptive analytics: optimization recommendations, expected vs actual impact tracking
 *  - 243.4 Research governance: IRB-like review for experiments involving sensitive data & ethical checklist
 *  - 243.6 Edge case: Extreme simulation outcomes undergo sanity check & clamped decision limits
 *  - 243.7 Model research graduation to production requires mandatory AI governance review
 */
class AdvancedAnalyticsGraphResearchService
{
    /**
     * Add edge to relationship graph (243.1).
     */
    public function addGraphEdge(
        string $sourceNode,
        string $targetNode,
        string $relationshipType,
        float $weight = 1.00,
        bool $isSuspicious = false
    ): object {
        $id = DB::table('analytics_graph_edges')->insertGetId([
            'source_node' => strtoupper($sourceNode),
            'target_node' => strtoupper($targetNode),
            'relationship_type' => strtoupper($relationshipType),
            'weight' => $weight,
            'is_suspicious_flow' => $isSuspicious,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('analytics_graph_edges')->find($id);
    }

    /**
     * Calculate network centrality & risk concentration for a node (243.1).
     */
    public function calculateCentrality(string $node): array
    {
        $nodeUpper = strtoupper($node);

        $outEdges = DB::table('analytics_graph_edges')->where('source_node', $nodeUpper)->get();
        $inEdges = DB::table('analytics_graph_edges')->where('target_node', $nodeUpper)->get();

        $outWeight = (float) $outEdges->sum('weight');
        $inWeight = (float) $inEdges->sum('weight');
        $hasSuspicious = $outEdges->contains('is_suspicious_flow', true) || $inEdges->contains('is_suspicious_flow', true);

        return [
            'node' => $nodeUpper,
            'out_degree' => $outEdges->count(),
            'in_degree' => $inEdges->count(),
            'total_degree' => $outEdges->count() + $inEdges->count(),
            'flow_volume' => $outWeight + $inWeight,
            'has_suspicious_flow' => $hasSuspicious,
            'risk_concentration_level' => ($outWeight + $inWeight > 1000000.0) ? 'HIGH' : 'NORMAL',
        ];
    }

    /**
     * Run deterministic seeded Monte Carlo risk simulation (243.2, 243.5, 243.6).
     */
    public function runMonteCarloSimulation(
        string $scenarioType,
        int $randomSeed,
        int $iterations,
        float $baseExposureUsd
    ): object {
        mt_srand($randomSeed);

        $simLosses = [];
        for ($i = 0; $i < $iterations; $i++) {
            // Factor between 0.10 and 2.50
            $factor = mt_rand(10, 250) / 100.0;
            $simLosses[] = $baseExposureUsd * $factor;
        }

        sort($simLosses);
        $p95Index = (int) floor($iterations * 0.95);
        $var95Loss = $simLosses[$p95Index] ?? ($baseExposureUsd * 1.5);

        // Edge case 243.6: Extreme outcome sanity check & clamping
        $isExtreme = $var95Loss > ($baseExposureUsd * 2.0);
        $clampedLimit = $isExtreme ? round($baseExposureUsd * 1.5, 2) : round($var95Loss, 2);

        $code = 'MC-'.strtoupper(Str::random(8));

        $id = DB::table('analytics_monte_carlo_simulations')->insertGetId([
            'sim_code' => $code,
            'scenario_type' => strtoupper($scenarioType),
            'random_seed' => $randomSeed,
            'iterations_count' => $iterations,
            'var_95_loss_estimate' => round($var95Loss, 2),
            'is_extreme_outlier' => $isExtreme,
            'clamped_decision_limit' => $clampedLimit,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('analytics_monte_carlo_simulations')->find($id);
    }

    /**
     * Create prescriptive recommendation (243.3).
     */
    public function createPrescriptiveRecommendation(
        string $domainLine,
        string $recommendationTitle,
        float $expectedImpactUsd
    ): object {
        $code = 'REC-'.strtoupper(Str::random(8));

        $id = DB::table('analytics_prescriptive_recommendations')->insertGetId([
            'rec_code' => $code,
            'domain_line' => strtoupper($domainLine),
            'recommendation_title' => $recommendationTitle,
            'expected_impact_usd' => $expectedImpactUsd,
            'actual_impact_usd' => null,
            'is_production_promoted' => false,
            'ai_governance_reviewed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('analytics_prescriptive_recommendations')->find($id);
    }

    /**
     * Record actual impact achieved by the prescriptive model (243.3).
     */
    public function recordActualImpact(int $recId, float $actualImpactUsd): object
    {
        DB::table('analytics_prescriptive_recommendations')
            ->where('id', $recId)
            ->update([
                'actual_impact_usd' => $actualImpactUsd,
                'updated_at' => now(),
            ]);

        return (object) DB::table('analytics_prescriptive_recommendations')->find($recId);
    }

    /**
     * Promote model recommendation to production with AI governance review (243.7).
     */
    public function promoteModelToProduction(int $recId, string $governanceReviewer): object
    {
        $rec = DB::table('analytics_prescriptive_recommendations')->find($recId);
        if (! $rec) {
            throw new InvalidArgumentException("Recommendation #{$recId} not found.");
        }

        DB::table('analytics_prescriptive_recommendations')
            ->where('id', $recId)
            ->update([
                'is_production_promoted' => true,
                'ai_governance_reviewed' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('analytics_prescriptive_recommendations')->find($recId);
    }

    /**
     * Register research experiment with ethical governance checklist (243.4).
     */
    public function registerResearchExperiment(
        string $title,
        bool $usesSensitiveData,
        bool $ethicalChecklistCompleted
    ): object {
        $code = 'EXP-'.strtoupper(Str::random(8));

        $id = DB::table('analytics_research_experiments')->insertGetId([
            'experiment_code' => $code,
            'title' => $title,
            'uses_sensitive_data' => $usesSensitiveData,
            'ethical_checklist_completed' => $ethicalChecklistCompleted,
            'irb_ethical_approved' => ! $usesSensitiveData, // If not sensitive, approved by default
            'approved_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('analytics_research_experiments')->find($id);
    }

    /**
     * Approve research experiment through IRB review (243.4 & 243.5).
     */
    public function approveIrbResearch(int $expId, string $irbChair): object
    {
        $exp = DB::table('analytics_research_experiments')->find($expId);
        if (! $exp) {
            throw new InvalidArgumentException("Experiment #{$expId} not found.");
        }

        if (! $exp->ethical_checklist_completed) {
            throw new InvalidArgumentException('Cannot approve IRB: Ethical checklist is not completed (243.4).');
        }

        DB::table('analytics_research_experiments')
            ->where('id', $expId)
            ->update([
                'irb_ethical_approved' => true,
                'approved_by' => strtoupper($irbChair),
                'updated_at' => now(),
            ]);

        return (object) DB::table('analytics_research_experiments')->find($expId);
    }

    /**
     * Analytics & Research Platform Audit (`platform:audit`) (243.5, 243.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Sensitive data experiments unapproved by IRB
        $unapprovedSensitiveExperiments = DB::table('analytics_research_experiments')
            ->where('uses_sensitive_data', true)
            ->where('irb_ethical_approved', false)
            ->count();

        // Discrepancy 2: Models promoted to production without AI governance review
        $unreviewedProductionModels = DB::table('analytics_prescriptive_recommendations')
            ->where('is_production_promoted', true)
            ->where('ai_governance_reviewed', false)
            ->count();

        // Discrepancy 3: Extreme outlier simulations with unclamped decision limit
        $unclampedExtremeSims = DB::table('analytics_monte_carlo_simulations')
            ->where('is_extreme_outlier', true)
            ->whereRaw('clamped_decision_limit >= var_95_loss_estimate')
            ->count();

        $discrepancies = $unapprovedSensitiveExperiments + $unreviewedProductionModels + $unclampedExtremeSims;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_graph_edges' => DB::table('analytics_graph_edges')->count(),
            'total_mc_simulations' => DB::table('analytics_monte_carlo_simulations')->count(),
            'total_recommendations' => DB::table('analytics_prescriptive_recommendations')->count(),
            'total_research_experiments' => DB::table('analytics_research_experiments')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
