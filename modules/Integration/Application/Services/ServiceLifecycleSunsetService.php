<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ServiceLifecycleSunsetService (Fase 366)
 *
 * Implements:
 *  - 366.1 Service/API lifecycle states (SUPPORTED, DEPRECATED, SUNSET)
 *  - 366.3 Safe retirement: credentials revoked and archived evidence retained
 *  - 366.4 Tests: Active consumer blocks sunset absent waiver; sunset revokes credentials; api:audit clean
 *  - 366.5 Edge case: Active consumers unable to migrate require formal dated waiver before sunset is executed
 *  - 366.6 Risk: Unarchived data loss prevented before endpoint retirement
 */
class ServiceLifecycleSunsetService
{
    /**
     * Register API service with consumer count and initial lifecycle status (366.1).
     */
    public function registerApiService(
        string $apiServiceCode,
        string $lifecycleState,
        int $activeConsumers
    ): object {
        $code = strtoupper($apiServiceCode);

        $id = DB::table('platform_api_service_sunsets')->insertGetId([
            'api_service_code' => $code,
            'current_lifecycle_state' => strtoupper($lifecycleState),
            'active_consumer_count' => $activeConsumers,
            'has_valid_dated_waiver' => false,
            'archived_evidence_retained' => false,
            'credentials_revoked' => false,
            'sunset_completed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_api_service_sunsets')->find($id);
    }

    /**
     * Execute safe service sunset with active consumer, waiver, and archival verification (366.2, 366.4, 366.5 Edge Case, 366.6 Risk).
     */
    public function executeSunset(
        string $apiServiceCode,
        bool $hasDatedWaiver,
        bool $archivedEvidenceRetained
    ): object {
        $code = strtoupper($apiServiceCode);
        $service = DB::table('platform_api_service_sunsets')->where('api_service_code', $code)->first();

        if (! $service) {
            throw new InvalidArgumentException("API service '{$apiServiceCode}' not found.");
        }

        // Edge case 366.5: Active consumers block sunset unless a valid dated waiver is attached
        if ($service->active_consumer_count > 0 && ! $hasDatedWaiver) {
            throw new InvalidArgumentException("Sunset blocked: Active consumers ({$service->active_consumer_count}) require a formal dated migration waiver before sunset (366.5).");
        }

        // Core gate 366.6 Risk: Archiving evidence mandatory before sunset
        if (! $archivedEvidenceRetained) {
            throw new InvalidArgumentException('Sunset blocked: Archiving evidence mandatory before retirement (366.6).');
        }

        DB::table('platform_api_service_sunsets')
            ->where('api_service_code', $code)
            ->update([
                'current_lifecycle_state' => 'SUNSET',
                'has_valid_dated_waiver' => $hasDatedWaiver,
                'archived_evidence_retained' => true,
                'credentials_revoked' => true, // 366.3 Credentials revoked
                'sunset_completed' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('platform_api_service_sunsets')->where('api_service_code', $code)->first();
    }

    /**
     * Platform API Sunset Audit (`api:audit`) (366.4, 366.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Sunset completed with active consumers and no waiver
        $unwaivedActiveSunsets = DB::table('platform_api_service_sunsets')
            ->where('sunset_completed', true)
            ->where('active_consumer_count', '>', 0)
            ->where('has_valid_dated_waiver', false)
            ->count();

        // Discrepancy 2: Sunset completed without archiving evidence or without credential revocation
        $unarchivedSunsets = DB::table('platform_api_service_sunsets')
            ->where('sunset_completed', true)
            ->where(function ($query) {
                $query->where('archived_evidence_retained', false)
                    ->orWhere('credentials_revoked', false);
            })
            ->count();

        $discrepancies = $unwaivedActiveSunsets + $unarchivedSunsets;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_services' => DB::table('platform_api_service_sunsets')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
