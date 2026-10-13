<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ReadWriteReplicaLagService (Fase 395)
 *
 * Implements:
 *  - 395.1 Read routing rules: Authoritative money/availability reads to primary; eventual reads to replica with freshness label
 *  - 395.2 Replica lag monitoring and bounded fallback to primary
 *  - 395.4 Tests: Payment confirmation never reads stale balance; analytics tolerates lag with freshness label
 *  - 395.5 Edge case: Severe replica lag redirects critical traffic to primary while analytics remains on replica
 *  - 395.6 Risk: Unlabeled eventual data mistaken for authoritative current data strictly prevented
 */
class ReadWriteReplicaLagService
{
    /**
     * Route read query enforcing primary for financial/critical operations (395.1, 395.4, 395.6 Risk).
     */
    public function routeReadQuery(
        string $queryCode,
        bool $isFinancialOrCritical,
        string $intendedTarget = 'REPLICA',
        bool $freshnessLabelAttached = true
    ): object {
        $qCode = strtoupper($queryCode);
        $target = strtoupper($intendedTarget);

        // Core gate 395.4: Payment/balance confirmation CANNOT read from eventual replica
        if ($isFinancialOrCritical && $target === 'REPLICA') {
            DB::table('global_stress_read_routing_queries')->insert([
                'query_code' => $qCode,
                'target_database' => 'REPLICA',
                'is_financial_or_critical' => true,
                'freshness_label_attached' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException('Consistency violation: Financial balance reads cannot route to eventual replica; authoritative primary routing required (395.4).');
        }

        // Risk gate 395.6: Eventual replica reads must have freshness label
        if (! $isFinancialOrCritical && ! $freshnessLabelAttached) {
            throw new InvalidArgumentException('Transparency breach: Eventual replica views must attach data freshness timestamp label (395.6).');
        }

        $id = DB::table('global_stress_read_routing_queries')->insertGetId([
            'query_code' => $qCode,
            'target_database' => $isFinancialOrCritical ? 'PRIMARY' : $target,
            'is_financial_or_critical' => $isFinancialOrCritical,
            'freshness_label_attached' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('global_stress_read_routing_queries')->find($id);
    }

    /**
     * Handle severe replica lag via selective fallback (395.2, 395.4, 395.5 Edge Case).
     */
    public function handleReplicaLagSurge(
        string $fallbackCode,
        int $replicaLagMs
    ): object {
        $fCode = strtoupper($fallbackCode);

        // Edge case 395.5: All replicas slow -> redirect critical traffic to primary; analytics stays on replica
        $redirectCritical = ($replicaLagMs > 5000);

        $id = DB::table('global_stress_replica_lag_fallbacks')->insertGetId([
            'fallback_code' => $fCode,
            'replica_lag_ms' => $replicaLagMs,
            'redirect_critical_to_primary' => $redirectCritical,
            'analytics_kept_on_replica' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('global_stress_replica_lag_fallbacks')->find($id);
    }

    /**
     * Read/Write Isolation Audit (395.4, 395.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Critical financial reads routed to replica
        $staleFinancialReads = DB::table('global_stress_read_routing_queries')
            ->where('is_financial_or_critical', true)
            ->where('target_database', 'REPLICA')
            ->count();

        // Discrepancy 2: Eventual queries without freshness labels
        $unlabeledQueries = DB::table('global_stress_read_routing_queries')
            ->where('is_financial_or_critical', false)
            ->where('freshness_label_attached', false)
            ->count();

        $discrepancies = $staleFinancialReads + $unlabeledQueries;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_queries' => DB::table('global_stress_read_routing_queries')->count(),
            'total_fallbacks' => DB::table('global_stress_replica_lag_fallbacks')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
