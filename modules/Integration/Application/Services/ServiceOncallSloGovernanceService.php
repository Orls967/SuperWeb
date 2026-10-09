<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ServiceOncallSloGovernanceService (Fase 363)
 *
 * Implements:
 *  - 363.1 Named owner, deputy, and on-call rotation for critical services
 *  - 363.2 Error-budget policy: Freeze releases when SLO budget exhausted unless exception approved
 *  - 363.4 Tests: Ownerless critical service fails readiness; budget exhaustion blocks release; platform:audit clean
 *  - 363.5 Edge case: Critical service without on-call fails readiness gate and cannot release
 *  - 363.6 Risk: Uncontrolled outages due to neglected error budget exhaustion prevented
 */
class ServiceOncallSloGovernanceService
{
    /**
     * Register service and evaluate operational readiness gate (363.1, 363.4, 363.5 Edge Case).
     */
    public function registerServiceReadiness(
        string $serviceCode,
        string $serviceName,
        string $criticalityTier,
        ?string $namedOwner = null,
        ?string $oncallRotationId = null
    ): object {
        $sCode = strtoupper($serviceCode);
        $tier = strtoupper($criticalityTier);

        // Core gate 363.4: Critical service without owner fails readiness
        if ($tier === 'CRITICAL' && empty($namedOwner)) {
            throw new InvalidArgumentException("Operational readiness failure: Critical service '{$serviceCode}' must have a designated named owner (363.4).");
        }

        // Edge case 363.5: Critical service without on-call fails readiness
        if ($tier === 'CRITICAL' && empty($oncallRotationId)) {
            throw new InvalidArgumentException("Operational readiness failure: Critical service '{$serviceCode}' cannot release without active on-call rotation (363.5).");
        }

        $id = DB::table('platform_service_oncall_registries')->insertGetId([
            'service_code' => $sCode,
            'service_name' => $serviceName,
            'criticality_tier' => $tier,
            'named_owner' => $namedOwner ? strtoupper($namedOwner) : null,
            'oncall_rotation_id' => $oncallRotationId ? strtoupper($oncallRotationId) : null,
            'readiness_passed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_service_oncall_registries')->find($id);
    }

    /**
     * Evaluate release eligibility against error-budget policy (363.2 & 363.4).
     */
    public function evaluateReleaseEligibility(
        string $releaseCode,
        string $serviceCode,
        float $remainingBudgetPct,
        bool $exceptionApproved = false
    ): object {
        $rCode = strtoupper($releaseCode);
        $sCode = strtoupper($serviceCode);

        $budgetExhausted = ($remainingBudgetPct <= 0.00);

        // Core gate 363.4: Budget exhaustion freezes release unless exception approved
        $releaseFrozen = ($budgetExhausted && ! $exceptionApproved);
        if ($releaseFrozen) {
            DB::table('platform_slo_error_budget_releases')->insert([
                'release_code' => $rCode,
                'service_code' => $sCode,
                'remaining_error_budget_pct' => $remainingBudgetPct,
                'budget_exhausted' => true,
                'accountable_exception_approved' => false,
                'release_frozen' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException("SLO error budget exhausted ({$remainingBudgetPct}%): Release frozen until reliability recovery (363.2).");
        }

        $id = DB::table('platform_slo_error_budget_releases')->insertGetId([
            'release_code' => $rCode,
            'service_code' => $sCode,
            'remaining_error_budget_pct' => $remainingBudgetPct,
            'budget_exhausted' => $budgetExhausted,
            'accountable_exception_approved' => $exceptionApproved,
            'release_frozen' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_slo_error_budget_releases')->find($id);
    }

    /**
     * Platform SLO & Service Ownership Audit (`platform:audit`) (363.4, 363.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Critical services marked readiness_passed without owner or on-call
        $unreadyCriticalServices = DB::table('platform_service_oncall_registries')
            ->where('criticality_tier', 'CRITICAL')
            ->where('readiness_passed', true)
            ->where(function ($query) {
                $query->whereNull('named_owner')
                    ->orWhereNull('oncall_rotation_id');
            })
            ->count();

        // Discrepancy 2: Releases executed with exhausted budget and without exception approval
        $unauthorizedExhaustedReleases = DB::table('platform_slo_error_budget_releases')
            ->where('budget_exhausted', true)
            ->where('accountable_exception_approved', false)
            ->where('release_frozen', false)
            ->count();

        $discrepancies = $unreadyCriticalServices + $unauthorizedExhaustedReleases;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_services' => DB::table('platform_service_oncall_registries')->count(),
            'total_releases' => DB::table('platform_slo_error_budget_releases')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
