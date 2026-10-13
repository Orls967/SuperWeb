<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * DataValueCostTransparencyService (Fase 342)
 *
 * Implements:
 *  - 342.2 Query cost transparency with domain budget threshold tracking and surge alerts
 *  - 342.3 Data asset business value attribution requiring Finance methodology review
 *  - 342.4 Tests: Attribution method documented; cost accurate; data:audit clean
 *  - 342.5 Edge case: Query costs surging beyond domain monthly budget trigger immediate budget alert
 *  - 342.6 Risk: Inconsistent value attribution prevented by enforcing mandatory Finance signoff before investment decisions
 */
class DataValueCostTransparencyService
{
    /**
     * Track query execution cost and detect domain budget overage (342.2, 342.4, 342.5 Edge Case).
     */
    public function trackQueryCost(
        string $trackingCode,
        string $domainName,
        string $consumerId,
        float $queryCostUsd,
        float $domainBudgetUsd
    ): object {
        $tCode = strtoupper($trackingCode);
        $dName = strtoupper($domainName);

        // Calculate month-to-date total cost for domain including this query
        $priorCost = (float) DB::table('data_product_query_cost_trackers')
            ->where('domain_name', $dName)
            ->sum('query_cost_usd');

        $mtdCost = round($priorCost + $queryCostUsd, 2);

        // Edge case 342.5: Surge beyond monthly budget triggers active budget alert
        $alertTriggered = ($mtdCost > $domainBudgetUsd);

        $id = DB::table('data_product_query_cost_trackers')->insertGetId([
            'tracking_code' => $tCode,
            'domain_name' => $dName,
            'consumer_id' => strtoupper($consumerId),
            'query_cost_usd' => $queryCostUsd,
            'domain_budget_usd' => $domainBudgetUsd,
            'month_to_date_cost_usd' => $mtdCost,
            'budget_alert_triggered' => $alertTriggered,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('data_product_query_cost_trackers')->find($id);
    }

    /**
     * Record business value attribution with mandatory Finance review (342.3, 342.4, 342.6 Risk).
     */
    public function attributeBusinessValue(
        string $attributionCode,
        string $datasetName,
        string $useCase,
        string $method,
        float $attributedValueUsd,
        bool $financeApproved
    ): object {
        $aCode = strtoupper($attributionCode);
        $mMethod = strtoupper($method);

        // Finance approval gate 342.6 Risk
        if (! $financeApproved) {
            throw new InvalidArgumentException('Financial governance breach: Data value attribution cannot be used for investment decisions without Finance methodology sign-off (342.6).');
        }

        $id = DB::table('data_asset_business_value_attributions')->insertGetId([
            'attribution_code' => $aCode,
            'dataset_name' => strtoupper($datasetName),
            'use_case_title' => $useCase,
            'attribution_method' => $mMethod,
            'attributed_value_usd' => $attributedValueUsd,
            'finance_reviewed_and_approved' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('data_asset_business_value_attributions')->find($id);
    }

    /**
     * Data Platform Value & Cost Audit (`data:audit`) (342.4, 342.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Attributions without Finance approval
        $unapprovedAttributions = DB::table('data_asset_business_value_attributions')
            ->where('finance_reviewed_and_approved', false)
            ->count();

        // Discrepancy 2: Month to date cost exceeds budget without alert triggered
        $unalertedOverages = DB::table('data_product_query_cost_trackers')
            ->whereRaw('month_to_date_cost_usd > domain_budget_usd')
            ->where('budget_alert_triggered', false)
            ->count();

        $discrepancies = $unapprovedAttributions + $unalertedOverages;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_query_tracks' => DB::table('data_product_query_cost_trackers')->count(),
            'total_attributions' => DB::table('data_asset_business_value_attributions')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
