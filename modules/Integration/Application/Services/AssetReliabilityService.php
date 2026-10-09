<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AssetReliabilityService (Fase 214)
 *
 * Implements:
 *  - 214.2 Predictive condition monitoring generating idempotent work orders
 *  - 214.4 Critical spare parts availability gating for repairs
 */
class AssetReliabilityService
{
    /**
     * Record sensor health index and trigger maintenance work order when threshold drops below 40.
     */
    public function evaluateAssetHealth(string $assetCode, string $assetClass, float $healthScore): object
    {
        $existing = DB::table('ops_asset_health_predictions')->where('asset_code', strtoupper($assetCode))->first();
        $predicted = ($healthScore < 40.0);

        // Keep existing WO code if already created (idempotent trigger once)
        $woCode = $existing ? $existing->generated_work_order_code : null;
        if ($predicted && ! $woCode) {
            $woCode = 'WO-'.strtoupper(Str::random(8));
        } elseif (! $predicted) {
            $woCode = null;
        }

        DB::table('ops_asset_health_predictions')->updateOrInsert(
            ['asset_code' => strtoupper($assetCode)],
            [
                'asset_class' => strtoupper($assetClass),
                'health_index_score' => $healthScore,
                'failure_predicted' => $predicted,
                'generated_work_order_code' => $woCode,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('ops_asset_health_predictions')->where('asset_code', strtoupper($assetCode))->first();
    }

    /**
     * Verify spare part availability before authorising physical repair.
     */
    public function checkSparePartAvailability(string $partCode, int $requiredQuantity = 1): bool
    {
        $part = DB::table('ops_asset_spare_parts')->where('part_code', strtoupper($partCode))->first();
        if (! $part) {
            return false;
        }

        return (int) $part->stock_on_hand >= $requiredQuantity;
    }

    /**
     * Register or update spare part inventory.
     */
    public function setSparePartStock(string $partCode, string $partName, string $tier, int $stock, int $safetyStock): object
    {
        DB::table('ops_asset_spare_parts')->updateOrInsert(
            ['part_code' => strtoupper($partCode)],
            [
                'part_name' => $partName,
                'criticality_tier' => strtoupper($tier),
                'stock_on_hand' => $stock,
                'safety_stock_threshold' => $safetyStock,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('ops_asset_spare_parts')->where('part_code', strtoupper($partCode))->first();
    }

    /**
     * Quality audit gate (`ast:audit`).
     */
    public function audit(): array
    {
        // Critical parts with stock below safety stock threshold
        $criticalDepleted = DB::table('ops_asset_spare_parts')
            ->where('criticality_tier', 'CRITICAL')
            ->whereRaw('stock_on_hand < safety_stock_threshold')
            ->count();

        return [
            'status' => $criticalDepleted === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_assets_monitored' => DB::table('ops_asset_health_predictions')->count(),
            'total_spare_parts' => DB::table('ops_asset_spare_parts')->count(),
            'discrepancy_count' => $criticalDepleted,
        ];
    }
}
