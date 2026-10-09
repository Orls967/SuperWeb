<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EsgOperatingModelOwnershipService (Fase 441)
 *
 * Implements:
 *  - 441.1 ESG data owners, metric stewards, control owners & assurance provider formalized
 *  - 441.2 ESG management system & continual improvement
 *  - 441.3 ESG incentive linkage: leadership scorecards with verified outcomes and anti-gaming guardrails
 *  - 441.4 Tests: metric has named owner, incentive uses verified metrics only, esg:audit clean
 *  - 441.5 Edge case: Anti-gaming guardrail flag blocks executive incentive payout if gaming detected
 *  - 441.6 Risk: Metric steward departure requires formal ownership transfer; orphaned metrics flagged
 *  - 441.7 Evidence: ownership matrix, management review, incentive rule
 */
class EsgOperatingModelOwnershipService
{
    public function registerMetricOwnership(
        string $metricCode,
        string $topic,
        string $dataOwner,
        string $steward,
        string $assuranceProvider
    ): object {
        if (empty(trim($steward))) {
            throw new InvalidArgumentException("Ownership invalid: Metric must have a named steward to prevent orphaned status (441.1, 441.6).");
        }

        $id = DB::table('esg_metric_ownership_matrices')->insertGetId([
            'metric_code' => strtoupper($metricCode),
            'topic' => strtolower($topic),
            'data_owner_name' => $dataOwner,
            'metric_steward_name' => $steward,
            'assurance_provider_name' => $assuranceProvider,
            'is_orphaned' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_metric_ownership_matrices')->where('id', $id)->first();
    }

    /**
     * 441.6 Transfer ownership to avoid orphaned metric
     */
    public function transferMetricOwnership(string $metricCode, string $newSteward): object
    {
        $metric = DB::table('esg_metric_ownership_matrices')->where('metric_code', strtoupper($metricCode))->first();
        if (! $metric) {
            throw new InvalidArgumentException("Metric '{$metricCode}' not found.");
        }

        if (empty(trim($newSteward))) {
            DB::table('esg_metric_ownership_matrices')->where('id', $metric->id)->update([
                'is_orphaned' => true,
                'metric_steward_name' => 'UNASSIGNED',
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException("Transfer failed: New steward cannot be empty, metric marked as orphaned (441.6).");
        }

        DB::table('esg_metric_ownership_matrices')->where('id', $metric->id)->update([
            'metric_steward_name' => $newSteward,
            'is_orphaned' => false,
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_metric_ownership_matrices')->where('id', $metric->id)->first();
    }

    public function recordScorecard(
        string $scorecardCode,
        string $executiveId,
        string $metricCode,
        float $target,
        float $actual,
        bool $verifiedByAssurance = false
    ): object {
        $id = DB::table('esg_leadership_incentive_scorecards')->insertGetId([
            'scorecard_code' => strtoupper($scorecardCode),
            'executive_id' => $executiveId,
            'metric_code' => strtoupper($metricCode),
            'target_performance' => $target,
            'actual_performance' => $actual,
            'metric_verified_by_assurance' => $verifiedByAssurance,
            'anti_gaming_guardrail_cleared' => true,
            'incentive_bonus_payout' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_leadership_incentive_scorecards')->where('id', $id)->first();
    }

    /**
     * 441.3, 441.4, 441.5 Payout incentive bonus under verified assurance and anti-gaming guardrail
     */
    public function payoutEsgIncentive(string $scorecardCode, float $bonusAmount, bool $antiGamingCleared = true): object
    {
        $card = DB::table('esg_leadership_incentive_scorecards')->where('scorecard_code', strtoupper($scorecardCode))->first();
        if (! $card) {
            throw new InvalidArgumentException("Scorecard '{$scorecardCode}' not found.");
        }

        // 441.4 Metric must be independently verified by assurance
        if (! $card->metric_verified_by_assurance) {
            throw new InvalidArgumentException("Payout blocked: ESG performance outcome must be independently verified by external assurance provider (441.3, 441.4).");
        }

        // 441.5 Edge case: Anti-gaming guardrail violation blocks payout
        if (! $antiGamingCleared) {
            DB::table('esg_leadership_incentive_scorecards')->where('id', $card->id)->update([
                'anti_gaming_guardrail_cleared' => false,
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException("Payout blocked: Metric gaming / manipulation detected by sustainability counter-metric guardrail (441.3, 441.5).");
        }

        DB::table('esg_leadership_incentive_scorecards')->where('id', $card->id)->update([
            'incentive_bonus_payout' => $bonusAmount,
            'anti_gaming_guardrail_cleared' => true,
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_leadership_incentive_scorecards')->where('id', $card->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Orphaned metrics
        $orphaned = DB::table('esg_metric_ownership_matrices')->where('is_orphaned', true)->count();

        // Discrepancy 2: Incentive paid on unverified metrics
        $unverifiedPayouts = DB::table('esg_leadership_incentive_scorecards')
            ->where('incentive_bonus_payout', '>', 0)
            ->where('metric_verified_by_assurance', false)
            ->count();

        $total = $orphaned + $unverifiedPayouts;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'orphaned_metrics' => $orphaned,
            'unverified_payouts' => $unverifiedPayouts,
            'discrepancy_count' => $total,
        ];
    }
}
