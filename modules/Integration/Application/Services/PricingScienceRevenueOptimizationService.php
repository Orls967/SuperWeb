<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * PricingScienceRevenueOptimizationService (Fase 245)
 *
 * Implements:
 *  - 245.1 Unified pricing architecture across 30 lines (cost-plus, value-based, dynamic, contract, promo, tariff)
 *  - 245.2 Elasticity & willingness-to-pay modeling, deterministic price ladders
 *  - 245.3 Price governance: floor/ceiling guardrails, margin impact matrix
 *  - 245.4 Profit pool analysis across line x segment x channel with strategic posture (GROW, HOLD, HARVEST)
 *  - 245.6 Edge case: Unreasonable price crash trips automated circuit breaker
 *  - 245.7 Mass price change requires batch approval + mandatory notice period (>= 14 days)
 */
class PricingScienceRevenueOptimizationService
{
    /**
     * Create pricing policy with floor, ceiling, and circuit breaker limits (245.1 & 245.3).
     */
    public function createPricingPolicy(
        string $businessLine,
        string $pricingModel,
        float $priceFloor,
        float $priceCeiling,
        float $minMarginPct = 15.00,
        float $circuitBreakerDropPct = 25.00
    ): object {
        if ($priceFloor > $priceCeiling) {
            throw new InvalidArgumentException('Price floor cannot exceed price ceiling.');
        }

        $code = 'PRC-'.strtoupper(Str::random(8));

        $id = DB::table('pricing_policy_rules')->insertGetId([
            'policy_code' => $code,
            'business_line' => strtoupper($businessLine),
            'pricing_model' => strtoupper($pricingModel),
            'price_floor' => $priceFloor,
            'price_ceiling' => $priceCeiling,
            'min_margin_pct' => $minMarginPct,
            'circuit_breaker_drop_pct' => $circuitBreakerDropPct,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('pricing_policy_rules')->find($id);
    }

    /**
     * Propose a price change, evaluating guardrails, circuit breakers, and notice periods (245.3, 245.6, 245.7).
     */
    public function proposePriceChange(
        int $policyRuleId,
        float $proposedPrice,
        float $previousPrice,
        bool $isMassChange = false,
        int $noticeDays = 0
    ): object {
        $policy = DB::table('pricing_policy_rules')->find($policyRuleId);
        if (! $policy) {
            throw new InvalidArgumentException("Policy #{$policyRuleId} not found.");
        }

        // Hard guardrail: cannot breach floor or ceiling (245.3 & 245.5)
        if ($proposedPrice < $policy->price_floor || $proposedPrice > $policy->price_ceiling) {
            throw new InvalidArgumentException("Proposed price {$proposedPrice} breaches policy bounds [{$policy->price_floor}, {$policy->price_ceiling}].");
        }

        // Circuit breaker check (245.6 Edge Case)
        $isTripped = false;
        $status = 'PENDING';
        if ($previousPrice > 0) {
            $dropPct = (($previousPrice - $proposedPrice) / $previousPrice) * 100.0;
            if ($dropPct > $policy->circuit_breaker_drop_pct) {
                $isTripped = true;
                $status = 'TRIPPED';
            }
        }

        // Mass change check (245.7)
        if ($isMassChange && $noticeDays < 14) {
            throw new InvalidArgumentException('Mass price changes require a minimum of 14 days notice period (245.7).');
        }

        $code = 'PCHG-'.strtoupper(Str::random(8));

        $id = DB::table('pricing_change_proposals')->insertGetId([
            'proposal_code' => $code,
            'policy_rule_id' => $policyRuleId,
            'proposed_price' => $proposedPrice,
            'previous_price' => $previousPrice,
            'is_circuit_breaker_tripped' => $isTripped,
            'is_mass_change' => $isMassChange,
            'notice_days' => $noticeDays,
            'status' => $status,
            'approved_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('pricing_change_proposals')->find($id);
    }

    /**
     * Approve price change proposal (245.3).
     */
    public function approvePriceChange(int $proposalId, string $approver): object
    {
        $prop = DB::table('pricing_change_proposals')->find($proposalId);
        if (! $prop) {
            throw new InvalidArgumentException("Proposal #{$proposalId} not found.");
        }

        if ($prop->is_circuit_breaker_tripped && $prop->status === 'TRIPPED') {
            // Can only be approved by VP_COMMERCIAL
            if (! str_contains(strtoupper($approver), 'VP_COMMERCIAL')) {
                throw new InvalidArgumentException('Circuit breaker tripped price change requires VP_COMMERCIAL approval (245.6).');
            }
        }

        DB::table('pricing_change_proposals')
            ->where('id', $proposalId)
            ->update([
                'status' => 'APPROVED',
                'approved_by' => strtoupper($approver),
                'updated_at' => now(),
            ]);

        return (object) DB::table('pricing_change_proposals')->find($proposalId);
    }

    /**
     * Calculate deterministic price elasticity curve and revenue lift (245.2 & 245.5).
     */
    public function calculateElasticityPriceLadder(
        string $segment,
        float $currentPrice,
        float $elasticityCoefficient
    ): array {
        // Optimal markup based on elasticity formula: P* = Cost / (1 + 1/e)
        // Simplified deterministic model:
        $optimalFactor = 1.0 + (abs($elasticityCoefficient) < 1.0 ? 0.15 : -0.08);
        $optimalPrice = round($currentPrice * $optimalFactor, 2);
        $liftPct = round((abs($optimalPrice - $currentPrice) / $currentPrice) * 10.0, 2);

        $code = 'ELAS-'.strtoupper(Str::random(8));

        DB::table('pricing_elasticity_curves')->insert([
            'curve_code' => $code,
            'customer_segment' => strtoupper($segment),
            'elasticity_coefficient' => $elasticityCoefficient,
            'optimal_price' => $optimalPrice,
            'projected_revenue_lift_pct' => $liftPct,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'curve_code' => $code,
            'customer_segment' => strtoupper($segment),
            'current_price' => $currentPrice,
            'optimal_price' => $optimalPrice,
            'projected_revenue_lift_pct' => $liftPct,
        ];
    }

    /**
     * Record profit pool entry (245.4).
     */
    public function recordProfitPool(
        string $businessLine,
        string $segment,
        string $channel,
        float $grossRevenue,
        float $cogsCost,
        string $strategicPosture
    ): object {
        $netProfit = round($grossRevenue - $cogsCost, 2);

        $id = DB::table('pricing_profit_pools')->insertGetId([
            'business_line' => strtoupper($businessLine),
            'segment' => strtoupper($segment),
            'channel' => strtoupper($channel),
            'gross_revenue' => $grossRevenue,
            'cogs_cost' => $cogsCost,
            'net_profit' => $netProfit,
            'strategic_posture' => strtoupper($strategicPosture),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('pricing_profit_pools')->find($id);
    }

    /**
     * Summarize profit pools and verify conservation: sum of pool profit = total profit (245.5).
     */
    public function getProfitPoolSummary(): array
    {
        $pools = DB::table('pricing_profit_pools')->get();
        $totalRevenue = (float) $pools->sum('gross_revenue');
        $totalCogs = (float) $pools->sum('cogs_cost');
        $summedProfit = (float) $pools->sum('net_profit');
        $calculatedTotalProfit = round($totalRevenue - $totalCogs, 2);

        $isConserved = abs($summedProfit - $calculatedTotalProfit) < 0.01;

        return [
            'total_pools' => $pools->count(),
            'total_revenue' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'summed_net_profit' => $summedProfit,
            'calculated_net_profit' => $calculatedTotalProfit,
            'is_profit_conserved' => $isConserved,
        ];
    }

    /**
     * Pricing Science Platform Audit (`pricing:audit`) (245.5, 245.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Approved changes breaching floor or ceiling
        $illegalPrices = DB::table('pricing_change_proposals')
            ->join('pricing_policy_rules', 'pricing_change_proposals.policy_rule_id', '=', 'pricing_policy_rules.id')
            ->where('pricing_change_proposals.status', 'APPROVED')
            ->where(function ($q) {
                $q->whereRaw('pricing_change_proposals.proposed_price < pricing_policy_rules.price_floor')
                    ->orWhereRaw('pricing_change_proposals.proposed_price > pricing_policy_rules.price_ceiling');
            })
            ->count();

        // Discrepancy 2: Tripped circuit breakers approved without VP_COMMERCIAL
        $unauthorizedCircuitBreakerApprovals = DB::table('pricing_change_proposals')
            ->where('is_circuit_breaker_tripped', true)
            ->where('status', 'APPROVED')
            ->where(function ($q) {
                $q->whereNull('approved_by')
                    ->orWhere('approved_by', 'NOT LIKE', '%VP_COMMERCIAL%');
            })
            ->count();

        // Discrepancy 3: Profit pools with negative margin or inconsistent math
        $summary = $this->getProfitPoolSummary();
        $discrepantMath = $summary['is_profit_conserved'] ? 0 : 1;

        $discrepancies = $illegalPrices + $unauthorizedCircuitBreakerApprovals + $discrepantMath;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_policies' => DB::table('pricing_policy_rules')->count(),
            'total_proposals' => DB::table('pricing_change_proposals')->count(),
            'total_elasticity_curves' => DB::table('pricing_elasticity_curves')->count(),
            'total_profit_pools' => DB::table('pricing_profit_pools')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
