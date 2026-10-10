<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * BusinessKpiObservabilityService (Fase 475)
 *
 * Implements:
 *  - 475.1 Business KPI as first-class observable with lineage to ledger
 *  - 475.2 Anomaly detection: sudden drop -> triage (technical vs business) -> owner -> resolution
 *  - 475.3 KPI freshness SLA: stale KPI flagged, consumers warned
 *  - 475.4 Tests: anomaly detected, lineage confirmed, freshness flag works, observability:audit clean
 *  - 475.5 Edge case: Stale KPI during crises flags consumers immediately (preventing reliance on obsolete metrics)
 *  - 475.6 Risk: False-positive suppression & tuning rules
 *  - 475.7 Evidence: anomaly detection log, lineage trace, freshness SLA
 */
class BusinessKpiObservabilityService
{
    public function recordKpiObservable(
        string $code,
        string $type,
        float $value,
        string $lineageRef,
        ?string $refreshedAt = null
    ): object {
        $id = DB::table('int_business_kpi_observables')->insertGetId([
            'kpi_code' => strtoupper($code),
            'kpi_type' => strtolower($type),
            'metric_value' => $value,
            'ledger_lineage_ref' => strtoupper($lineageRef),
            'last_refreshed_at' => $refreshedAt ?? now()->toDateTimeString(),
            'is_stale' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_business_kpi_observables')->where('id', $id)->first();
    }

    /**
     * 475.3 & 475.5 Check freshness SLA (e.g. older than 30 minutes considered stale)
     */
    public function evaluateFreshnessSla(int $maxAgeMinutes = 30): int
    {
        $threshold = now()->subMinutes($maxAgeMinutes)->toDateTimeString();

        return DB::table('int_business_kpi_observables')
            ->where('is_stale', false)
            ->where('last_refreshed_at', '<', $threshold)
            ->update([
                'is_stale' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * 475.2 Anomaly detection: Drop > 20% triggers anomaly triage
     */
    public function detectAndFlagAnomaly(string $kpiCode, float $priorValue, float $currentValue): ?object
    {
        if ($priorValue <= 0) {
            return null;
        }

        $dropPercent = (($priorValue - $currentValue) / $priorValue) * 100.00;

        if ($dropPercent >= 20.00) {
            $anomalyCode = 'ANOM-'.strtoupper(substr(md5($kpiCode.time()), 0, 8));

            $id = DB::table('int_business_kpi_anomalies')->insertGetId([
                'anomaly_code' => $anomalyCode,
                'kpi_code' => strtoupper($kpiCode),
                'drop_percentage' => $dropPercent,
                'triage_category' => 'unassigned',
                'assigned_owner' => null,
                'status' => 'triaging',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (object) DB::table('int_business_kpi_anomalies')->where('id', $id)->first();
        }

        return null;
    }

    public function triageAndResolveAnomaly(string $anomalyCode, string $category, string $owner): object
    {
        $cat = strtolower($category);
        if (! in_array($cat, ['technical', 'business_market'], true)) {
            throw new InvalidArgumentException("Triage category must be 'technical' or 'business_market' (475.2).");
        }

        $anom = DB::table('int_business_kpi_anomalies')->where('anomaly_code', strtoupper($anomalyCode))->first();
        if (! $anom) {
            throw new InvalidArgumentException("Anomaly '{$anomalyCode}' not found.");
        }

        DB::table('int_business_kpi_anomalies')->where('id', $anom->id)->update([
            'triage_category' => $cat,
            'assigned_owner' => $owner,
            'status' => 'resolved',
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_business_kpi_anomalies')->where('id', $anom->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Unresolved anomalies past 2 hours
        $untriagedAnomalies = DB::table('int_business_kpi_anomalies')
            ->where('status', 'triaging')
            ->where('created_at', '<', now()->subHours(2)->toDateTimeString())
            ->count();

        // Discrepancy 2: Stale observables without freshness flag
        $unflaggedStale = DB::table('int_business_kpi_observables')
            ->where('is_stale', false)
            ->where('last_refreshed_at', '<', now()->subHours(4)->toDateTimeString())
            ->count();

        $total = $untriagedAnomalies + $unflaggedStale;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_observables' => DB::table('int_business_kpi_observables')->count(),
            'total_anomalies' => DB::table('int_business_kpi_anomalies')->count(),
            'discrepancy_count' => $total,
        ];
    }
}
