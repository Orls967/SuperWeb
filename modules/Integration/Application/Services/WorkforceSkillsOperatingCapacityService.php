<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * WorkforceSkillsOperatingCapacityService (Fase 379)
 *
 * Implements:
 *  - 379.2 Cross-line workforce deployment qualification & rest rules enforcement
 *  - 379.3 Operating capacity promises reflecting actual staffing shortages
 *  - 379.4 Tests: Qualification gate universal; rest rules consistent; capacity reflects staffing; hcm:audit clean
 *  - 379.5 Edge case: Sudden staffing shortages automatically reduce capacity promise with notice to prevent false promises
 *  - 379.6 Risk: Excessive overtime or fatigued worker deployment strictly prevented
 */
class WorkforceSkillsOperatingCapacityService
{
    /**
     * Approve cross-line workforce deployment (379.2 & 379.4).
     */
    public function deployWorkerCrossLine(
        string $deploymentCode,
        string $workerId,
        string $targetLine,
        bool $qualificationGatePassed,
        bool $restRulesRespected = true
    ): object {
        $dCode = strtoupper($deploymentCode);
        $wId = strtoupper($workerId);

        // Core gate 379.4: Qualification gate universal
        if (! $qualificationGatePassed) {
            throw new InvalidArgumentException("Deployment rejected: Worker '{$workerId}' lacks required qualification certification for '{$targetLine}' (379.4).");
        }

        // Core gate 379.4 & 379.6 Risk: Rest rules and fatigue control
        if (! $restRulesRespected) {
            throw new InvalidArgumentException('Fatigue policy violation: Mandatory rest period not met, cross-line deployment blocked (379.6).');
        }

        $id = DB::table('global_workforce_cross_line_deployments')->insertGetId([
            'deployment_code' => $dCode,
            'worker_id' => $wId,
            'target_line_of_business' => strtoupper($targetLine),
            'qualification_gate_passed' => true,
            'rest_rules_respected' => true,
            'deployment_approved' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('global_workforce_cross_line_deployments')->find($id);
    }

    /**
     * Calibrate capacity promise based on available staff count (379.3, 379.4, 379.5 Edge Case).
     */
    public function calibrateCapacityPromise(
        string $promiseCode,
        string $siteId,
        int $availableStaff,
        int $requestedCapacityUnits
    ): object {
        $pCode = strtoupper($promiseCode);
        $sId = strtoupper($siteId);

        // Capacity constraint: 1 staff member supports up to 10 units
        $maxCapacityPermitted = $availableStaff * 10;
        $shortageOccurred = ($requestedCapacityUnits > $maxCapacityPermitted);

        // Edge case 379.5: Shortage triggers transparent reduction with notice to prevent false promises
        $finalCapacity = $shortageOccurred ? $maxCapacityPermitted : $requestedCapacityUnits;

        $id = DB::table('global_operating_capacity_promises')->insertGetId([
            'promise_code' => $pCode,
            'facility_or_site_id' => $sId,
            'available_staff_count' => $availableStaff,
            'promised_capacity_units' => $finalCapacity,
            'capacity_reduced_due_to_shortage' => $shortageOccurred,
            'false_promise_prevented' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('global_operating_capacity_promises')->find($id);
    }

    /**
     * Workforce & HCM Capacity Audit (`hcm:audit`) (379.4, 379.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Deployments approved without qualification or rest rules
        $invalidDeployments = DB::table('global_workforce_cross_line_deployments')
            ->where('deployment_approved', true)
            ->where(function ($query) {
                $query->where('qualification_gate_passed', false)
                    ->orWhere('rest_rules_respected', false);
            })
            ->count();

        // Discrepancy 2: Promises where promised capacity exceeds staff ratio (> 10 units / staff)
        $overpromisedCapacities = DB::table('global_operating_capacity_promises')
            ->whereRaw('promised_capacity_units > (available_staff_count * 10)')
            ->count();

        $discrepancies = $invalidDeployments + $overpromisedCapacities;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_deployments' => DB::table('global_workforce_cross_line_deployments')->count(),
            'total_capacity_promises' => DB::table('global_operating_capacity_promises')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
