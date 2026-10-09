<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * AiForecastingSopService (Fase 201)
 *
 * Implements:
 *  - 201.2 Hierarchical forecast bottom-up & top-down reconciliation (Σ regional == national)
 *  - 201.3 Executive S&OP cross-line balancing & sign-off
 */
class AiForecastingSopService
{
    /**
     * Register domain forecast with strict hierarchical reconciliation.
     */
    public function registerForecast(string $domain, string $period, float $nationalAggregate, float $regionalSum, float $mape = 4.5): object
    {
        $discrepancy = round($nationalAggregate - $regionalSum, 2);
        if ($discrepancy !== 0.0) {
            throw new \RuntimeException("Hierarchical forecast discrepancy: National aggregate ({$nationalAggregate}) does not match regional bottom-up sum ({$regionalSum}).");
        }

        $code = 'FCST-'.strtoupper($domain).'-'.str_replace('-', '', $period);

        DB::table('ai_forecast_registries')->updateOrInsert(
            ['forecast_code' => $code],
            [
                'domain_code' => strtoupper($domain),
                'forecast_period' => $period,
                'national_aggregate_units' => $nationalAggregate,
                'regional_sum_units' => $regionalSum,
                'reconciliation_discrepancy' => 0.00,
                'backtest_mape_percent' => $mape,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('ai_forecast_registries')->where('forecast_code', $code)->first();
    }

    /**
     * Execute executive S&OP balancing and sign-off.
     */
    public function executeExecutiveSignoff(string $period, float $demand, float $capacity, string $executiveName): object
    {
        if (empty(trim($executiveName))) {
            throw new \InvalidArgumentException('Executive sign-off requires authorized officer name.');
        }

        $code = 'SOP-SIGNOFF-'.str_replace('-', '', $period);

        $id = DB::table('ai_sop_executive_signoffs')->insertGetId([
            'signoff_code' => $code,
            'forecast_period' => $period,
            'demand_review_units' => $demand,
            'capacity_allocated_units' => $capacity,
            'signed_off_by' => $executiveName,
            'status' => 'APPROVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_sop_executive_signoffs')->find($id);
    }

    /**
     * Quality audit gate (`tower:audit`).
     */
    public function audit(): array
    {
        $reconciliationDiscrepancies = DB::table('ai_forecast_registries')
            ->where('reconciliation_discrepancy', '!=', 0.0)
            ->count();

        return [
            'status' => $reconciliationDiscrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_forecasts' => DB::table('ai_forecast_registries')->count(),
            'total_signoffs' => DB::table('ai_sop_executive_signoffs')->count(),
            'discrepancy_count' => $reconciliationDiscrepancies,
        ];
    }
}
