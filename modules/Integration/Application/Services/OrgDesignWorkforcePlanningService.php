<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * OrgDesignWorkforcePlanningService (Fase 223)
 *
 * Implements:
 *  - 223.1 Org design across 30 lines & position hierarchy management
 *  - 223.2 Workforce planning, gap analysis & fulfillment strategy (Build/Buy/Borrow/Gig)
 *  - 223.3 Critical positions succession coverage & single-point-of-failure detection
 *  - 223.4 Headcount governance rejecting requisitions exceeding position budget
 *  - 223.6 Edge case: Sudden hiring freeze holding requisitions safely with reason without pipeline loss
 */
class OrgDesignWorkforcePlanningService
{
    /**
     * Create position definition.
     */
    public function createPosition(
        string $posCode,
        string $businessLine,
        string $countryCode,
        string $title,
        string $gradeBand,
        ?string $parentPosCode,
        int $budgetedHeadcount,
        bool $isCritical = false
    ): object {
        if ($parentPosCode !== null) {
            $parentExists = DB::table('hcm_positions')->where('position_code', strtoupper($parentPosCode))->exists();
            if (! $parentExists) {
                throw new \InvalidArgumentException("Parent position {$parentPosCode} does not exist.");
            }
        }

        $id = DB::table('hcm_positions')->insertGetId([
            'position_code' => strtoupper($posCode),
            'business_line' => strtoupper($businessLine),
            'country_code' => strtoupper($countryCode),
            'title' => $title,
            'grade_band' => strtoupper($gradeBand),
            'parent_position_code' => $parentPosCode ? strtoupper($parentPosCode) : null,
            'budgeted_headcount' => $budgetedHeadcount,
            'current_headcount' => 0,
            'is_critical' => $isCritical,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_positions')->find($id);
    }

    /**
     * Update reporting line while maintaining hierarchy integrity (223.1 & 223.5).
     */
    public function updateReportingLine(string $posCode, ?string $newParentPosCode): void
    {
        if ($newParentPosCode !== null) {
            if (strtoupper($posCode) === strtoupper($newParentPosCode)) {
                throw new \InvalidArgumentException("A position cannot report to itself.");
            }

            $parentExists = DB::table('hcm_positions')->where('position_code', strtoupper($newParentPosCode))->exists();
            if (! $parentExists) {
                throw new \InvalidArgumentException("Target parent position {$newParentPosCode} does not exist.");
            }
        }

        DB::table('hcm_positions')->where('position_code', strtoupper($posCode))->update([
            'parent_position_code' => $newParentPosCode ? strtoupper($newParentPosCode) : null,
            'updated_at' => now(),
        ]);
    }

    /**
     * Create headcount requisition with budget check (223.4 & 223.5) and freeze awareness (223.6).
     */
    public function createRequisition(string $posCode, int $requestedCount = 1, float $estimatedCost = 0): object
    {
        $pos = DB::table('hcm_positions')->where('position_code', strtoupper($posCode))->first();
        if (! $pos) {
            throw new \InvalidArgumentException("Position {$posCode} not found.");
        }

        // Budget Check (223.4 & 223.5): Headcount over budget is rejected
        if (($pos->current_headcount + $requestedCount) > $pos->budgeted_headcount) {
            throw new \InvalidArgumentException(
                "Headcount over budget: Requesting {$requestedCount} for position {$posCode} exceeds budgeted limit of {$pos->budgeted_headcount} (current: {$pos->current_headcount})."
            );
        }

        $reqCode = 'REQ-'.strtoupper(Str::random(8));

        // Edge Case 223.6: Hiring freeze holds requisition with reason, keeping pipeline intact
        $status = ($pos->status === 'FROZEN') ? 'FROZEN_HELD' : 'PENDING';
        $freezeReason = ($pos->status === 'FROZEN') ? 'Position is under active hiring freeze' : null;

        $id = DB::table('hcm_headcount_requisitions')->insertGetId([
            'requisition_code' => $reqCode,
            'position_code' => $pos->position_code,
            'requested_count' => $requestedCount,
            'estimated_cost' => $estimatedCost,
            'status' => $status,
            'frozen_reason' => $freezeReason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_headcount_requisitions')->find($id);
    }

    /**
     * Apply sudden hiring freeze to a business line (223.6 Edge Case).
     */
    public function applyHiringFreeze(string $businessLine, string $reason): void
    {
        DB::table('hcm_positions')
            ->where('business_line', strtoupper($businessLine))
            ->update([
                'status' => 'FROZEN',
                'updated_at' => now(),
            ]);

        // Hold active pending requisitions safely with reason, keeping talent pipeline intact
        DB::table('hcm_headcount_requisitions')
            ->join('hcm_positions', 'hcm_headcount_requisitions.position_code', '=', 'hcm_positions.position_code')
            ->where('hcm_positions.business_line', strtoupper($businessLine))
            ->where('hcm_headcount_requisitions.status', 'PENDING')
            ->update([
                'hcm_headcount_requisitions.status' => 'FROZEN_HELD',
                'hcm_headcount_requisitions.frozen_reason' => $reason,
                'hcm_headcount_requisitions.updated_at' => now(),
            ]);
    }

    /**
     * Fulfill requisition and increment position headcount.
     */
    public function fulfillRequisition(string $reqCode): void
    {
        $req = DB::table('hcm_headcount_requisitions')->where('requisition_code', strtoupper($reqCode))->first();
        if (! $req) {
            throw new \InvalidArgumentException("Requisition {$reqCode} not found.");
        }

        if ($req->status === 'FROZEN_HELD') {
            throw new \RuntimeException("Cannot fulfill requisition {$reqCode}: currently held under hiring freeze.");
        }

        DB::table('hcm_positions')->where('position_code', $req->position_code)->increment('current_headcount', $req->requested_count);

        DB::table('hcm_headcount_requisitions')->where('requisition_code', strtoupper($reqCode))->update([
            'status' => 'FULFILLED',
            'updated_at' => now(),
        ]);
    }

    /**
     * Register successor for a critical position (223.3).
     */
    public function addSuccessor(string $posCode, string $successorGoldenId, string $readinessLevel): object
    {
        $id = DB::table('hcm_succession_plans')->insertGetId([
            'position_code' => strtoupper($posCode),
            'successor_golden_id' => strtoupper($successorGoldenId),
            'readiness_level' => strtoupper($readinessLevel),
            'development_plan_status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_succession_plans')->find($id);
    }

    /**
     * Calculate critical positions succession coverage metric (223.3 & 223.5).
     */
    public function getSuccessionMetrics(): array
    {
        $criticalPositions = DB::table('hcm_positions')
            ->where('is_critical', true)
            ->get();

        $totalCritical = $criticalPositions->count();
        if ($totalCritical === 0) {
            return [
                'total_critical_positions' => 0,
                'covered_positions' => 0,
                'coverage_pct' => 100.0,
                'single_point_of_failures' => [],
            ];
        }

        $coveredCount = 0;
        $spofs = [];

        foreach ($criticalPositions as $pos) {
            $hasSuccessor = DB::table('hcm_succession_plans')
                ->where('position_code', $pos->position_code)
                ->exists();

            if ($hasSuccessor) {
                $coveredCount++;
            } else {
                $spofs[] = $pos->position_code;
            }
        }

        $coveragePct = round(($coveredCount / $totalCritical) * 100, 2);

        return [
            'total_critical_positions' => $totalCritical,
            'covered_positions' => $coveredCount,
            'coverage_pct' => $coveragePct,
            'single_point_of_failures' => $spofs,
        ];
    }

    /**
     * Record workforce planning demand & gap calculation (223.2).
     */
    public function planWorkforceDemand(
        string $period,
        string $functionName,
        int $demandFte,
        int $supplyFte,
        string $strategy
    ): object {
        $gap = $demandFte - $supplyFte;

        $id = DB::table('hcm_workforce_demands')->insertGetId([
            'planning_period' => strtoupper($period),
            'function_name' => $functionName,
            'demand_fte' => $demandFte,
            'supply_internal_fte' => $supplyFte,
            'gap_fte' => $gap,
            'fulfillment_strategy' => strtoupper($strategy),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_workforce_demands')->find($id);
    }

    /**
     * Quality audit gate (`hcm:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: Over-budget headcount violations
        $overbudget = DB::table('hcm_positions')
            ->whereRaw('current_headcount > budgeted_headcount')
            ->count();

        // Discrepancy 2: Broken reporting lines (parent does not exist)
        $brokenHierarchy = DB::table('hcm_positions as p')
            ->whereNotNull('p.parent_position_code')
            ->leftJoin('hcm_positions as parent', 'p.parent_position_code', '=', 'parent.position_code')
            ->whereNull('parent.position_code')
            ->count();

        $discrepancies = $overbudget + $brokenHierarchy;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_positions' => DB::table('hcm_positions')->count(),
            'total_requisitions' => DB::table('hcm_headcount_requisitions')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
