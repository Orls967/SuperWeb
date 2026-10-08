<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * SkillsEconomyInternalMobilityService (Fase 317)
 *
 * Implements:
 *  - 317.1 Internal talent gig exchange & manager approval flow
 *  - 317.2 Gig-to-permanent conversion with headcount governance approval
 *  - 317.3 Expertise marketplace with internal billing fee ledger
 *  - 317.4 Tests: Capacity guardrails enforced, conversion respects headcount approval, hcm:audit clean
 *  - 317.5 Edge case: Critical talent reassignment requires mandatory continuity plan & handover documentation
 *  - 317.6 Risk: Employee weekly capacity cap (<= 40 hrs total) prevents burnout and primary workload degradation
 */
class SkillsEconomyInternalMobilityService
{
    /**
     * Create internal gig project with hours & internal billing fee (317.1 & 317.3).
     */
    public function createGigProject(
        string $gigCode,
        string $projectName,
        string $businessUnit,
        int $hoursPerWeek,
        float $hourlyRateUsd,
        int $durationWeeks
    ): object {
        $code = strtoupper($gigCode);

        // Internal fee 317.3: Total project fee = hours * rate * weeks
        $totalFee = round($hoursPerWeek * $hourlyRateUsd * $durationWeeks, 2);

        $id = DB::table('internal_talent_gigs')->insertGetId([
            'gig_code' => $code,
            'project_name' => $projectName,
            'requesting_business_unit' => strtoupper($businessUnit),
            'required_capacity_hours_per_week' => $hoursPerWeek,
            'internal_hourly_rate_usd' => $hourlyRateUsd,
            'total_project_fee_usd' => $totalFee,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('internal_talent_gigs')->find($id);
    }

    /**
     * Assign employee to gig with capacity guardrail and manager approval (317.1, 317.4, 317.5, 317.6 Risk).
     */
    public function assignEmployeeToGig(
        string $assignmentCode,
        string $gigCode,
        string $employeeId,
        int $allocatedHoursPerWeek,
        int $existingWorkloadHoursPerWeek,
        bool $managerApprovalGranted,
        bool $continuityHandoverFiled = true
    ): object {
        $aCode = strtoupper($assignmentCode);
        $gCode = strtoupper($gigCode);

        // Capacity guardrail 317.4 & 317.6: Total hours cannot exceed 40 hours/week cap
        if (($existingWorkloadHoursPerWeek + $allocatedHoursPerWeek) > 40) {
            throw new InvalidArgumentException("Capacity overload breach: Combined weekly workload (" . ($existingWorkloadHoursPerWeek + $allocatedHoursPerWeek) . " hrs) exceeds 40 hours/week ceiling (317.6).");
        }

        // Manager approval check 317.1
        if (! $managerApprovalGranted) {
            throw new InvalidArgumentException("Manager approval violation: Gig assignments require formal manager visibility & approval (317.1).");
        }

        // Edge case 317.5: Mandatory continuity plan & handover documentation
        if (! $continuityHandoverFiled) {
            throw new InvalidArgumentException("Continuity risk: Reassigning critical talent requires mandatory handover & continuity documentation (317.5).");
        }

        $id = DB::table('internal_talent_assignments')->insertGetId([
            'assignment_code' => $aCode,
            'gig_code' => $gCode,
            'employee_id' => strtoupper($employeeId),
            'employee_allocated_hours_per_week' => $allocatedHoursPerWeek,
            'max_weekly_capacity_cap' => 40,
            'manager_approval_granted' => true,
            'continuity_handover_plan_filed' => true,
            'is_converted_to_permanent' => false,
            'headcount_approval_for_permanent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('internal_talent_assignments')->find($id);
    }

    /**
     * Convert exceptional gig performer to permanent placement (317.2 & 317.4).
     */
    public function convertGigToPermanent(string $assignmentCode, bool $headcountApproved): object
    {
        $aCode = strtoupper($assignmentCode);
        $assignment = DB::table('internal_talent_assignments')->where('assignment_code', $aCode)->first();
        if (! $assignment) {
            throw new InvalidArgumentException("Assignment '{$assignmentCode}' not found.");
        }

        // Headcount check 317.4: Permanent conversion must strictly obey headcount budget approval
        if (! $headcountApproved) {
            throw new InvalidArgumentException("Headcount governance breach: Permanent talent conversion strictly requires authorized headcount budget approval (317.4).");
        }

        DB::table('internal_talent_assignments')
            ->where('assignment_code', $aCode)
            ->update([
                'is_converted_to_permanent' => true,
                'headcount_approval_for_permanent' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('internal_talent_assignments')->where('assignment_code', $aCode)->first();
    }

    /**
     * Human Capital Management Audit (`hcm:audit`) (317.4, 317.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Assignments without manager approval
        $unapprovedAssignments = DB::table('internal_talent_assignments')
            ->where('manager_approval_granted', false)
            ->count();

        // Discrepancy 2: Permanent conversions without headcount approval
        $unapprovedConversions = DB::table('internal_talent_assignments')
            ->where('is_converted_to_permanent', true)
            ->where('headcount_approval_for_permanent', false)
            ->count();

        $discrepancies = $unapprovedAssignments + $unapprovedConversions;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_gigs' => DB::table('internal_talent_gigs')->count(),
            'total_assignments' => DB::table('internal_talent_assignments')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
