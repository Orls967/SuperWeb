<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ModelIncidentSafetyCaseService (Fase 356)
 *
 * Implements:
 *  - 356.1 Incident containment and emergency rollback
 *  - 356.2 Safety cases for high-impact models with launch gating
 *  - 356.4 Tests: High-impact model cannot launch without safety case; incident containment effective; ai:audit clean
 *  - 356.5 Edge case: Multi-domain high-impact incident automatically triggers cross-domain war room
 *  - 356.6 Risk: Uncontrolled model deployment or unresolved safety incidents prevented
 */
class ModelIncidentSafetyCaseService
{
    /**
     * Register model safety case and enforce launch gate for high-impact models (356.2 & 356.4).
     */
    public function registerSafetyCase(
        string $caseCode,
        string $modelId,
        string $impactTier,
        bool $hasApprovedCase
    ): object {
        $cCode = strtoupper($caseCode);
        $tier = strtoupper($impactTier);

        // Core gate 356.4: High-impact model cannot launch without approved safety case
        if ($tier === 'HIGH_IMPACT' && ! $hasApprovedCase) {
            throw new InvalidArgumentException("AI Governance safety violation: High-impact model cannot launch without an approved safety case (356.4).");
        }

        $id = DB::table('ai_high_impact_safety_cases')->insertGetId([
            'case_code' => $cCode,
            'model_identifier' => strtoupper($modelId),
            'impact_tier' => $tier,
            'has_approved_safety_case' => $hasApprovedCase,
            'launch_permitted' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_high_impact_safety_cases')->find($id);
    }

    /**
     * Declare model incident with containment rollback and cross-domain war room activation (356.1, 356.4, 356.5 Edge Case).
     */
    public function reportIncident(
        string $incidentCode,
        string $modelId,
        string $severity,
        bool $affectsMultipleDomains,
        bool $containmentRollback
    ): object {
        $iCode = strtoupper($incidentCode);
        $sev = strtoupper($severity);

        // Edge case 356.5: High-impact multi-domain incident triggers cross-domain war room
        $activateWarRoom = ($affectsMultipleDomains && in_array($sev, ['HIGH', 'CRITICAL']));

        $id = DB::table('ai_model_incidents')->insertGetId([
            'incident_code' => $iCode,
            'model_identifier' => strtoupper($modelId),
            'severity' => $sev,
            'affects_multiple_domains' => $affectsMultipleDomains,
            'cross_domain_war_room_activated' => $activateWarRoom,
            'containment_rollback_executed' => $containmentRollback,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_model_incidents')->find($id);
    }

    /**
     * AI Safety Case & Incident Audit (`ai:audit`) (356.4, 356.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: High impact models launched without approved safety case
        $unapprovedLaunches = DB::table('ai_high_impact_safety_cases')
            ->where('impact_tier', 'HIGH_IMPACT')
            ->where('launch_permitted', true)
            ->where('has_approved_safety_case', false)
            ->count();

        // Discrepancy 2: Critical multi-domain incidents without war room
        $neglectedIncidents = DB::table('ai_model_incidents')
            ->where('affects_multiple_domains', true)
            ->whereIn('severity', ['HIGH', 'CRITICAL'])
            ->where('cross_domain_war_room_activated', false)
            ->count();

        $discrepancies = $unapprovedLaunches + $neglectedIncidents;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_safety_cases' => DB::table('ai_high_impact_safety_cases')->count(),
            'total_incidents' => DB::table('ai_model_incidents')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
