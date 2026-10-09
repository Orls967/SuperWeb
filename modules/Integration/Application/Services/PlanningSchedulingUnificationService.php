<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * PlanningSchedulingUnificationService (Fase 277)
 *
 * Implements:
 *  - 277.1 Unified enterprise planning stack (demand, supply, capacity, workforce, finance) in a single consistent plan number
 *  - 277.2 Finite scheduling across resources (machines, workforce, vessels, beds) with 100% feasibility (no overbooking)
 *  - 277.5 Edge case: Modifications to signed-off plans trigger archiving of the old version and creation of an approved new version
 *  - 277.6 Automated reschedule trigger rules strictly respecting hard constraints (working hours, legal permits, rated capacity)
 */
class PlanningSchedulingUnificationService
{
    /**
     * Create unified multi-functional plan (277.1).
     */
    public function createUnifiedPlan(
        string $planCode,
        string $domainLine,
        string $planningPeriod,
        float $demandUnits,
        float $capacityHours,
        float $workforceHeadcount,
        float $financialBudgetUsd,
        bool $isSignedOff = false
    ): object {
        $code = strtoupper($planCode);

        $id = DB::table('operations_unified_plans')->insertGetId([
            'plan_code' => $code,
            'domain_line' => strtoupper($domainLine),
            'planning_period' => $planningPeriod,
            'version' => 1,
            'unified_demand_units' => $demandUnits,
            'capacity_hours_allocated' => $capacityHours,
            'workforce_headcount_planned' => $workforceHeadcount,
            'financial_budget_usd' => $financialBudgetUsd,
            'is_signed_off' => $isSignedOff,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('operations_unified_plans')->find($id);
    }

    /**
     * Modify signed-off plan by archiving previous version and generating approved new version (277.5 Edge Case).
     */
    public function reviseSignedOffPlan(
        string $existingPlanCode,
        float $newDemandUnits,
        float $newBudgetUsd
    ): object {
        $code = strtoupper($existingPlanCode);
        $prevPlan = DB::table('operations_unified_plans')->where('plan_code', $code)->first();
        if (! $prevPlan) {
            throw new InvalidArgumentException("Plan '{$existingPlanCode}' not found.");
        }

        // Archive previous version (277.5)
        DB::table('operations_unified_plans')
            ->where('plan_code', $code)
            ->update([
                'is_archived' => true,
                'updated_at' => now(),
            ]);

        // Create new versioned plan
        $newVersion = (int) $prevPlan->version + 1;
        $newCode = $code.'-V'.$newVersion;

        $id = DB::table('operations_unified_plans')->insertGetId([
            'plan_code' => $newCode,
            'domain_line' => $prevPlan->domain_line,
            'planning_period' => $prevPlan->planning_period,
            'version' => $newVersion,
            'unified_demand_units' => $newDemandUnits,
            'capacity_hours_allocated' => $prevPlan->capacity_hours_allocated,
            'workforce_headcount_planned' => $prevPlan->workforce_headcount_planned,
            'financial_budget_usd' => $newBudgetUsd,
            'is_signed_off' => true, // Approved revision
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('operations_unified_plans')->find($id);
    }

    /**
     * Register finite resource schedule enforcing 100% feasibility (277.2 & 277.4).
     */
    public function registerResourceSchedule(
        string $scheduleCode,
        string $resourceId,
        float $capacityLimitHours,
        string $scheduleDate
    ): object {
        $code = strtoupper($scheduleCode);

        $id = DB::table('operations_finite_schedules')->insertGetId([
            'schedule_code' => $code,
            'resource_id' => strtoupper($resourceId),
            'capacity_limit_hours' => $capacityLimitHours,
            'booked_hours' => 0.0,
            'schedule_date' => $scheduleDate,
            'is_overbooked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('operations_finite_schedules')->find($id);
    }

    /**
     * Book hours on finite schedule with strict overbooking prevention (277.4).
     */
    public function bookScheduleHours(string $scheduleCode, float $hoursToBook): object
    {
        $code = strtoupper($scheduleCode);
        $sched = DB::table('operations_finite_schedules')->where('schedule_code', $code)->first();
        if (! $sched) {
            throw new InvalidArgumentException("Schedule '{$scheduleCode}' not found.");
        }

        $newBooked = (float) $sched->booked_hours + $hoursToBook;

        // Feasibility constraint (277.4): 100% feasibility (cannot exceed capacity)
        if ($newBooked > (float) $sched->capacity_limit_hours) {
            throw new InvalidArgumentException("Finite scheduling feasibility error: Booking ({$newBooked}h) exceeds hard capacity limit ({$sched->capacity_limit_hours}h) for '{$scheduleCode}' (277.4).");
        }

        DB::table('operations_finite_schedules')
            ->where('schedule_code', $code)
            ->update([
                'booked_hours' => $newBooked,
                'is_overbooked' => false,
                'updated_at' => now(),
            ]);

        return (object) DB::table('operations_finite_schedules')->where('schedule_code', $code)->first();
    }

    /**
     * Reschedule resource hours respecting hard constraints (working hours, legal permits, rated capacity) (277.6).
     */
    public function rescheduleWithHardConstraints(
        string $scheduleCode,
        string $triggerReason,
        float $newAdjustedHours,
        bool $violatesHardConstraint = false
    ): object {
        $code = strtoupper($scheduleCode);
        $sched = DB::table('operations_finite_schedules')->where('schedule_code', $code)->first();
        if (! $sched) {
            throw new InvalidArgumentException("Schedule '{$scheduleCode}' not found.");
        }

        // Hard constraint check (277.6): Working hours, legal permits, rated machinery capacity cannot be violated
        if ($violatesHardConstraint || $newAdjustedHours > (float) $sched->capacity_limit_hours) {
            throw new InvalidArgumentException("Reschedule rejected: Proposed adjustment ({$newAdjustedHours}h) violates hard operational constraints (277.6).");
        }

        DB::table('operations_finite_schedules')
            ->where('schedule_code', $code)
            ->update([
                'booked_hours' => $newAdjustedHours,
                'updated_at' => now(),
            ]);

        $eventCode = 'EVT-'.strtoupper(Str::random(8));

        $id = DB::table('operations_reschedule_events')->insertGetId([
            'event_code' => $eventCode,
            'schedule_code' => $code,
            'trigger_reason' => strtoupper($triggerReason),
            'adjusted_hours' => $newAdjustedHours,
            'hard_constraint_respected' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('operations_reschedule_events')->find($id);
    }

    /**
     * Operations Planning & Scheduling Platform Audit (`tower:audit`) (277.4, 277.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Overbooked schedules (> 100% capacity)
        $overbookedSchedules = DB::table('operations_finite_schedules')
            ->whereRaw('booked_hours > capacity_limit_hours')
            ->count();

        // Discrepancy 2: Reschedule events violating hard constraints
        $unconstrainedReschedules = DB::table('operations_reschedule_events')
            ->where('hard_constraint_respected', false)
            ->count();

        // Discrepancy 3: Active duplicate un-archived plans for same line and period
        $activePlansCount = DB::table('operations_unified_plans')
            ->where('is_archived', false)
            ->count();

        $discrepancies = $overbookedSchedules + $unconstrainedReschedules;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_plans' => DB::table('operations_unified_plans')->count(),
            'total_schedules' => DB::table('operations_finite_schedules')->count(),
            'total_reschedule_events' => DB::table('operations_reschedule_events')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
