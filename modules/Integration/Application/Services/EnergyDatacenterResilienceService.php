<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EnergyDatacenterResilienceService (Fase 383)
 *
 * Implements:
 *  - 383.1 DC workload placement respecting data residency and criticality
 *  - 383.2 Grid events trigger workload shelter for priority services first
 *  - 383.4 Tests: Residency constraint enforced; critical priority honored; egy:audit clean
 *  - 383.5 Edge case: Grid emergency shelters critical workloads first, throttles non-critical with notice
 *  - 383.6 Risk: Data residency breach or unnotified throttling prevented
 */
class EnergyDatacenterResilienceService
{
    /**
     * Place DC workload enforcing sovereign data residency (383.1 & 383.4).
     */
    public function placeWorkload(
        string $workloadCode,
        string $targetJurisdiction,
        string $dataResidencyJurisdiction,
        bool $isCriticalPriority = false
    ): object {
        $wCode = strtoupper($workloadCode);
        $tJur = strtoupper($targetJurisdiction);
        $rJur = strtoupper($dataResidencyJurisdiction);

        // Core gate 383.4: Data residency constraint must match
        if ($tJur !== $rJur) {
            DB::table('global_data_center_workload_placements')->insert([
                'workload_code' => $wCode,
                'target_jurisdiction' => $tJur,
                'data_residency_jurisdiction' => $rJur,
                'residency_constraint_enforced' => false,
                'is_critical_priority' => $isCriticalPriority,
                'placement_status' => 'REJECTED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException("Data residency violation: Placement target '{$targetJurisdiction}' breaches statutory residency '{$dataResidencyJurisdiction}' (383.4).");
        }

        $id = DB::table('global_data_center_workload_placements')->insertGetId([
            'workload_code' => $wCode,
            'target_jurisdiction' => $tJur,
            'data_residency_jurisdiction' => $rJur,
            'residency_constraint_enforced' => true,
            'is_critical_priority' => $isCriticalPriority,
            'placement_status' => 'PLACED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('global_data_center_workload_placements')->find($id);
    }

    /**
     * Handle grid event sheltering critical workloads first and throttling non-critical (383.2, 383.4, 383.5 Edge Case).
     */
    public function triggerGridResiliencePlan(
        string $eventCode,
        string $gridStatus,
        bool $criticalSheltered = true,
        bool $nonCriticalThrottledWithNotice = true
    ): object {
        $eCode = strtoupper($eventCode);
        $status = strtoupper($gridStatus);

        // Edge case 383.5: In emergency, critical workloads MUST be sheltered and non-critical throttled WITH notice
        if ($status === 'EMERGENCY' && (! $criticalSheltered || ! $nonCriticalThrottledWithNotice)) {
            throw new InvalidArgumentException('Resilience plan failure: Grid emergency requires critical workloads to be sheltered and non-critical throttled with notice (383.5).');
        }

        $id = DB::table('global_grid_event_resilience_plans')->insertGetId([
            'event_code' => $eCode,
            'grid_status' => $status,
            'critical_workloads_sheltered' => $criticalSheltered,
            'non_critical_throttled_with_notice' => $nonCriticalThrottledWithNotice,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('global_grid_event_resilience_plans')->find($id);
    }

    /**
     * Energy & Telemetry Resilience Audit (`egy:audit` + `tlx:audit`) (383.4, 383.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Workloads placed violating data residency
        $residencyBreaches = DB::table('global_data_center_workload_placements')
            ->where('placement_status', 'PLACED')
            ->where('residency_constraint_enforced', false)
            ->count();

        // Discrepancy 2: Grid emergency events where critical workloads were not sheltered
        $unshelteredEmergencies = DB::table('global_grid_event_resilience_plans')
            ->where('grid_status', 'EMERGENCY')
            ->where('critical_workloads_sheltered', false)
            ->count();

        $discrepancies = $residencyBreaches + $unshelteredEmergencies;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_placements' => DB::table('global_data_center_workload_placements')->count(),
            'total_grid_plans' => DB::table('global_grid_event_resilience_plans')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
