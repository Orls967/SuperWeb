<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EcosystemHealthNetworkGovernanceService (Fase 375)
 *
 * Implements:
 *  - 375.2 Fair access policies: prevent self-preferencing ranking boosts for platform products
 *  - 375.3 Network health interventions with bounded subsidy caps
 *  - 375.4 Tests: Ranking reproducible; self-preferencing audit detects violation; subsidy capped; ecosystem:audit clean
 *  - 375.5 Edge case: Intervention causing net-negative market distortion must be evaluated and terminated
 *  - 375.6 Risk: Market concentration and unfair preferential manipulation prevented
 */
class EcosystemHealthNetworkGovernanceService
{
    /**
     * Compute search ranking enforcing strict fair access without self-preferencing boosts (375.2 & 375.4).
     */
    public function computeFairRanking(
        string $rankingCode,
        string $itemCode,
        bool $isPlatformOwned,
        float $organicScore,
        bool $forceSelfPreferencingBoost = false
    ): object {
        $rCode = strtoupper($rankingCode);
        $iCode = strtoupper($itemCode);

        // Core gate 375.2 & 375.4: Self-preferencing is forbidden
        if ($isPlatformOwned && $forceSelfPreferencingBoost) {
            DB::table('ecosystem_fairness_search_rankings')->insert([
                'ranking_code' => $rCode,
                'item_code' => $iCode,
                'is_platform_owned_item' => true,
                'self_preferencing_boost_applied' => true,
                'reproducible_rank_score' => $organicScore + 50.0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException('Fair competition violation: Self-preferencing boosts for platform items strictly prohibited (375.2).');
        }

        $id = DB::table('ecosystem_fairness_search_rankings')->insertGetId([
            'ranking_code' => $rCode,
            'item_code' => $iCode,
            'is_platform_owned_item' => $isPlatformOwned,
            'self_preferencing_boost_applied' => false,
            'reproducible_rank_score' => $organicScore,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ecosystem_fairness_search_rankings')->find($id);
    }

    /**
     * Issue ecosystem health subsidy intervention with strict cap and distortion termination (375.3, 375.4, 375.5 Edge Case).
     */
    public function issueHealthIntervention(
        string $interventionCode,
        string $partnerCode,
        float $subsidyAmountUsd,
        float $subsidyCapUsd = 5000.00,
        bool $hasMarketDistortion = false
    ): object {
        $iCode = strtoupper($interventionCode);
        $pCode = strtoupper($partnerCode);

        // Core gate 375.4: Subsidy amount cannot exceed cap
        if ($subsidyAmountUsd > $subsidyCapUsd) {
            throw new InvalidArgumentException("Intervention budget breach: Subsidy amount (\${$subsidyAmountUsd}) exceeds maximum cap (\${$subsidyCapUsd}) (375.4).");
        }

        // Edge case 375.5: Distortion detected triggers immediate termination
        $terminated = $hasMarketDistortion;

        $id = DB::table('ecosystem_health_interventions')->insertGetId([
            'intervention_code' => $iCode,
            'partner_code' => $pCode,
            'subsidy_amount_usd' => $subsidyAmountUsd,
            'subsidy_cap_usd' => $subsidyCapUsd,
            'net_negative_market_distortion' => $hasMarketDistortion,
            'intervention_terminated' => $terminated,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ecosystem_health_interventions')->find($id);
    }

    /**
     * Ecosystem Health Governance Audit (`ecosystem:audit`) (375.4, 375.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Platform-owned rankings with self-preferencing boost
        $selfPreferencedRankings = DB::table('ecosystem_fairness_search_rankings')
            ->where('self_preferencing_boost_applied', true)
            ->count();

        // Discrepancy 2: Subsidies exceeding caps or active market-distorting interventions
        $unboundedSubsidies = DB::table('ecosystem_health_interventions')
            ->whereColumn('subsidy_amount_usd', '>', 'subsidy_cap_usd')
            ->count();

        $activeDistortions = DB::table('ecosystem_health_interventions')
            ->where('net_negative_market_distortion', true)
            ->where('intervention_terminated', false)
            ->count();

        $discrepancies = $selfPreferencedRankings + $unboundedSubsidies + $activeDistortions;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_rankings' => DB::table('ecosystem_fairness_search_rankings')->count(),
            'total_interventions' => DB::table('ecosystem_health_interventions')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
