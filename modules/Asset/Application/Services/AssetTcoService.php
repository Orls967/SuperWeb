<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetInsurance;
use Modules\Asset\Domain\Models\AssetWorkOrder;

/**
 * 31.7 Total Cost of Ownership per aset: penyusutan + pemeliharaan +
 * asuransi (+ biaya operasional dari pemakaian bila dicatat).
 *
 * Rekomendasi ganti (simulasi): TCO kumulatif ≥ 60% biaya perolehan
 * ATAU pemeliharaan kumulatif ≥ 30% biaya perolehan ATAU kondisi buruk.
 */
class AssetTcoService
{
    public const REPLACE_MAINTENANCE_RATIO = 0.30;

    public const REPLACE_TCO_RATIO = 0.60;

    /**
     * @return array{asset_id: string, depreciation_idr: int, maintenance_idr: int,
     *   insurance_idr: int, fuel_idr: int, total_tco_idr: int,
     *   acquisition_cost_idr: int, tco_percent: float, recommend_replace: bool}
     */
    public function tco(Asset $asset): array
    {
        $depreciation = (int) DB::table('ast_depreciations')
            ->where('asset_id', $asset->id)
            ->where('book', 'commercial')
            ->sum('amount_idr');

        $maintenance = (int) AssetWorkOrder::where('asset_id', $asset->id)
            ->where('status', 'completed')
            ->sum('total_cost_idr');

        $insurance = (int) AssetInsurance::where('asset_id', $asset->id)
            ->sum('annual_premium_idr');

        // Biaya bahan bakar dari biaya WO berlabel "fuel"/BBM (opsional, simulasi).
        $fuel = (int) DB::table('ast_work_orders')
            ->where('asset_id', $asset->id)
            ->where('status', 'completed')
            ->where(function ($q) {
                $q->where('description', 'like', '%bbm%')
                    ->orWhere('description', 'like', '%bahan bakar%')
                    ->orWhere('description', 'like', '%fuel%');
            })
            ->sum('total_cost_idr');

        $total = $depreciation + $maintenance + $insurance + $fuel;
        $cost = (int) $asset->acquisition_cost_idr + (int) $asset->landed_cost_idr;
        $percent = $cost > 0 ? round($total * 100 / $cost, 2) : 0.0;

        $recommend = $cost > 0 && (
            $total >= $cost * self::REPLACE_TCO_RATIO
            || $maintenance >= $cost * self::REPLACE_MAINTENANCE_RATIO
            || $asset->condition === 'poor'
            || $asset->condition === 'broken'
        );

        return [
            'asset_id' => $asset->id,
            'depreciation_idr' => $depreciation,
            'maintenance_idr' => $maintenance,
            'insurance_idr' => $insurance,
            'fuel_idr' => $fuel,
            'total_tco_idr' => $total,
            'acquisition_cost_idr' => $cost,
            'tco_percent' => $percent,
            'recommend_replace' => $recommend,
        ];
    }
}
