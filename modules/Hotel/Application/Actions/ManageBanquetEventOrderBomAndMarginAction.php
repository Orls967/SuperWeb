<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\BanquetProductionSheet;
use Modules\Shared\Application\BaseAction;

class ManageBanquetEventOrderBomAndMarginAction extends BaseAction
{
    /**
     * Manage Banquet Event Order (BEO) Recipe BOM costing and enforce 40% gross margin threshold.
     *
     * @return array{production_sheet: BanquetProductionSheet, total_revenue_idr: int, total_production_cost_idr: int, gross_margin_pct: float}
     */
    public function handle(
        string $miceContractId,
        string $packageName,
        int $paxCount,
        int $packagePricePerPaxIdr,
        int $fnbMaterialsCostIdr,
        int $avEquipmentVendorCostIdr,
        int $decorFloristVendorCostIdr
    ): array {
        return DB::transaction(function () use (
            $miceContractId,
            $packageName,
            $paxCount,
            $packagePricePerPaxIdr,
            $fnbMaterialsCostIdr,
            $avEquipmentVendorCostIdr,
            $decorFloristVendorCostIdr
        ) {
            $totalProductionCostIdr = $fnbMaterialsCostIdr + $avEquipmentVendorCostIdr + $decorFloristVendorCostIdr;
            $totalRevenueIdr = $paxCount * $packagePricePerPaxIdr;

            if ($totalRevenueIdr <= 0) {
                throw new \InvalidArgumentException('Total revenue must be positive.');
            }

            $grossMarginPct = round((($totalRevenueIdr - $totalProductionCostIdr) / $totalRevenueIdr) * 100, 2);

            if ($grossMarginPct < 40.0) {
                throw new \RuntimeException(
                    "Banquet order rejected: Gross margin {$grossMarginPct}% is below the mandatory 40.0% floor threshold."
                );
            }

            $sheet = BanquetProductionSheet::create([
                'id' => (string) Str::uuid(),
                'mice_contract_id' => $miceContractId,
                'menu_package_name' => $packageName,
                'pax_count' => $paxCount,
                'fnb_materials_cost_idr' => $fnbMaterialsCostIdr,
                'av_equipment_vendor_cost_idr' => $avEquipmentVendorCostIdr,
                'decor_florist_vendor_cost_idr' => $decorFloristVendorCostIdr,
                'total_production_cost_idr' => $totalProductionCostIdr,
            ]);

            $this->audit(
                action: 'hotel.banquet.bom_managed',
                auditable: $sheet,
                oldValues: null,
                newValues: [
                    'mice_contract_id' => $miceContractId,
                    'package_name' => $packageName,
                    'pax_count' => $paxCount,
                    'total_production_cost_idr' => $totalProductionCostIdr,
                    'gross_margin_pct' => $grossMarginPct,
                ],
                correlationId: "htl_beo_{$sheet->id}",
                impactType: 'banquet_margin_control'
            );

            return [
                'production_sheet' => $sheet,
                'total_revenue_idr' => $totalRevenueIdr,
                'total_production_cost_idr' => $totalProductionCostIdr,
                'gross_margin_pct' => $grossMarginPct,
            ];
        });
    }
}
