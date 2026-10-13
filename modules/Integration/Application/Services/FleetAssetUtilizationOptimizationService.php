<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * FleetAssetUtilizationOptimizationService (Fase 278)
 *
 * Implements:
 *  - 278.1 Enterprise asset utilization framework across mobile & stationary equipment (idle cost, revenue, net contribution)
 *  - 278.2 Asset allocation optimizer with strict maintenance & certified crew constraints
 *  - 278.3 Asset lifecycle decision engine (repair vs replace TCO analysis)
 *  - 278.5 Edge case: Net contribution is evaluated as primary metric (higher utilization with lower revenue does not count as success)
 *  - 278.6 Capital replacement decisions require pre-requisite budget encumbrance & capex financing approvals
 */
class FleetAssetUtilizationOptimizationService
{
    /**
     * Record asset utilization metrics prioritizing net contribution (278.1 & 278.5 Edge Case).
     */
    public function recordAssetUtilization(
        string $assetCode,
        string $assetCategory,
        float $operatingHours,
        float $idleHours,
        float $generatedRevenueUsd,
        float $idleHourlyCostUsd = 50.0
    ): object {
        $code = strtoupper($assetCode);
        $totalHours = $operatingHours + $idleHours;
        $utilizationPct = ($totalHours > 0) ? round(($operatingHours / $totalHours) * 100.0, 2) : 0.0;
        $idleCost = round($idleHours * $idleHourlyCostUsd, 2);

        // Net contribution = generated revenue minus idle holding cost (278.5)
        $netContribution = round($generatedRevenueUsd - $idleCost, 2);

        DB::table('asset_utilization_metrics')->updateOrInsert(
            ['asset_code' => $code],
            [
                'asset_category' => strtoupper($assetCategory),
                'total_operating_hours' => $operatingHours,
                'idle_hours' => $idleHours,
                'utilization_pct' => $utilizationPct,
                'idle_cost_usd' => $idleCost,
                'generated_revenue_usd' => $generatedRevenueUsd,
                'net_contribution_usd' => $netContribution,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('asset_utilization_metrics')->where('asset_code', $code)->first();
    }

    /**
     * Allocate asset to demand contract respecting maintenance and certified crew constraints (278.2 & 278.4).
     */
    public function allocateAsset(
        string $assetCode,
        string $demandContractRef,
        bool $maintenanceCleared = true,
        bool $crewCertifiedCleared = true
    ): object {
        $code = strtoupper($assetCode);

        // Hard constraint guard (278.2 & 278.4)
        if (! $maintenanceCleared || ! $crewCertifiedCleared) {
            throw new InvalidArgumentException("Asset allocation rejected: Maintenance clearance or certified crew requirements not satisfied for '{$assetCode}' (278.2).");
        }

        $allocCode = 'ALLOC-'.strtoupper(Str::random(8));

        $id = DB::table('asset_allocations')->insertGetId([
            'allocation_code' => $allocCode,
            'asset_code' => $code,
            'demand_contract_ref' => strtoupper($demandContractRef),
            'maintenance_constraint_cleared' => true,
            'crew_certified_cleared' => true,
            'status' => 'ALLOCATED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('asset_allocations')->find($id);
    }

    /**
     * Compute repair-or-replace lifecycle decision and enforce budget encumbrance (278.3 & 278.6 Edge Case).
     */
    public function evaluateRepairOrReplace(
        string $assetCode,
        float $cumulativeRepairCostUsd,
        float $replacementCostUsd,
        float $salvageValueUsd,
        bool $budgetEncumbered = false
    ): object {
        $code = strtoupper($assetCode);

        // Replace recommended if cumulative repairs exceed 60% of replacement cost minus salvage (278.3)
        $threshold = ($replacementCostUsd - $salvageValueUsd) * 0.60;
        $recommendation = ($cumulativeRepairCostUsd >= $threshold) ? 'REPLACE_NEW_ASSET' : 'REPAIR_AND_CONTINUE';

        $decisionCode = 'DEC-LIFE-'.strtoupper(Str::random(8));

        $id = DB::table('asset_lifecycle_decisions')->insertGetId([
            'decision_code' => $decisionCode,
            'asset_code' => $code,
            'cumulative_repair_cost_usd' => $cumulativeRepairCostUsd,
            'estimated_replacement_cost_usd' => $replacementCostUsd,
            'residual_salvage_value_usd' => $salvageValueUsd,
            'recommended_action' => $recommendation,
            'is_budget_encumbered' => $budgetEncumbered,
            'is_capex_approved' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('asset_lifecycle_decisions')->find($id);
    }

    /**
     * Approve capex replacement requiring prior budget encumbrance (278.6).
     */
    public function approveCapexReplacement(string $decisionCode): object
    {
        $code = strtoupper($decisionCode);
        $decision = DB::table('asset_lifecycle_decisions')->where('decision_code', $code)->first();
        if (! $decision) {
            throw new InvalidArgumentException("Decision '{$decisionCode}' not found.");
        }

        // Edge case 278.6: Replacement requires prior budget encumbrance
        if (! $decision->is_budget_encumbered) {
            throw new InvalidArgumentException('Capex replacement approval rejected: Budget encumbrance required prior to financing sign-off (278.6).');
        }

        DB::table('asset_lifecycle_decisions')
            ->where('decision_code', $code)
            ->update([
                'is_capex_approved' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('asset_lifecycle_decisions')->where('decision_code', $code)->first();
    }

    /**
     * Asset Platform Audit (`ast:audit`) (278.4, 278.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Allocations missing maintenance or crew clearance
        $unconstrainedAllocations = DB::table('asset_allocations')
            ->where('status', 'ALLOCATED')
            ->where(function ($q) {
                $q->where('maintenance_constraint_cleared', false)
                    ->orWhere('crew_certified_cleared', false);
            })
            ->count();

        // Discrepancy 2: Approved capex replacements without budget encumbrance
        $unencumberedCapexApprovals = DB::table('asset_lifecycle_decisions')
            ->where('is_capex_approved', true)
            ->where('is_budget_encumbered', false)
            ->count();

        // Discrepancy 3: Assets with negative net contribution not reviewed
        $negativeContributions = DB::table('asset_utilization_metrics')
            ->where('net_contribution_usd', '<', 0.0)
            ->count();

        $discrepancies = $unconstrainedAllocations + $unencumberedCapexApprovals;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_metrics' => DB::table('asset_utilization_metrics')->count(),
            'total_allocations' => DB::table('asset_allocations')->count(),
            'total_decisions' => DB::table('asset_lifecycle_decisions')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
