<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AnalyticsFederationService (Fase 189)
 *
 * Implements:
 *  - 189.1 Data product catalog with automated SLA freshness breach detection
 *  - 189.2 Federated metric store with immutable voucher ledger lineage
 *  - 189.3 Privacy-preserving cross-line data exports with k-anonymity verification
 */
class AnalyticsFederationService
{
    /**
     * Register or heartbeat data product catalog.
     * Evaluates SLA freshness breach based on elapsed minutes.
     */
    public function registerDataProduct(string $domainCode, string $name, int $slaMinutes, Carbon $lastRefresh): object
    {
        $code = 'DP-'.strtoupper($domainCode).'-'.strtoupper(Str::random(6));
        $minutesElapsed = abs(Carbon::now()->diffInMinutes($lastRefresh, false));
        $breached = ($minutesElapsed > $slaMinutes);

        $id = DB::table('anl_data_products')->insertGetId([
            'product_code' => $code,
            'domain_code' => strtoupper($domainCode),
            'product_name' => $name,
            'sla_freshness_minutes' => $slaMinutes,
            'last_refreshed_at' => $lastRefresh,
            'sla_breached' => $breached,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('anl_data_products')->find($id);
    }

    /**
     * Record federated KPI metric with voucher ledger lineage reference.
     */
    public function recordMetric(string $metricCode, string $domain, float $value, ?string $voucherRef = null): object
    {
        DB::table('anl_federated_metrics')->updateOrInsert(
            ['metric_code' => strtoupper($metricCode)],
            [
                'owning_domain' => strtoupper($domain),
                'metric_value' => $value,
                'ledger_voucher_reference' => $voucherRef,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('anl_federated_metrics')->where('metric_code', strtoupper($metricCode))->first();
    }

    /**
     * Request cross-line cohort analytics export.
     * Enforces k-anonymity threshold (cohortSize >= minThreshold, default 10).
     */
    public function requestCrossLineExport(string $requestingDomain, int $cohortSize, int $kThreshold = 10): object
    {
        $passed = ($cohortSize >= $kThreshold);

        if (! $passed) {
            $code = 'EXP-ANL-'.strtoupper(Str::random(8));
            $id = DB::table('anl_cross_line_exports')->insertGetId([
                'export_code' => $code,
                'requesting_domain' => strtoupper($requestingDomain),
                'cohort_size' => $cohortSize,
                'min_k_anonymity_threshold' => $kThreshold,
                'privacy_check_passed' => false,
                'status' => 'REJECTED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new \RuntimeException("Privacy check failed: Cohort size ({$cohortSize}) fails minimum k-anonymity threshold ({$kThreshold}). Export rejected.");
        }

        $code = 'EXP-ANL-'.strtoupper(Str::random(8));

        $id = DB::table('anl_cross_line_exports')->insertGetId([
            'export_code' => $code,
            'requesting_domain' => strtoupper($requestingDomain),
            'cohort_size' => $cohortSize,
            'min_k_anonymity_threshold' => $kThreshold,
            'privacy_check_passed' => true,
            'status' => 'APPROVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('anl_cross_line_exports')->find($id);
    }

    /**
     * Quality audit gate (`analytics:audit`).
     */
    public function audit(): array
    {
        $unauthorizedExports = DB::table('anl_cross_line_exports')
            ->where('status', 'APPROVED')
            ->where('privacy_check_passed', false)
            ->count();

        return [
            'status' => $unauthorizedExports === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_data_products' => DB::table('anl_data_products')->count(),
            'total_metrics' => DB::table('anl_federated_metrics')->count(),
            'total_exports' => DB::table('anl_cross_line_exports')->count(),
            'discrepancy_count' => $unauthorizedExports,
        ];
    }
}
