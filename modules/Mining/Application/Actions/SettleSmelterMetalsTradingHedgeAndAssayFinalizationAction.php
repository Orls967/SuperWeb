<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\MetalsTradingPosition;
use Modules\Mining\Domain\Models\MetalWarehouseReceipt;
use Modules\Mining\Domain\Models\SmelterRun;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class SettleSmelterMetalsTradingHedgeAndAssayFinalizationAction extends BaseAction
{
    /**
     * Settle smelter mass balance recovery, mark-to-market metals trading hedge, and issue warehouse collateral receipt.
     *
     * @return array{smelter_run: SmelterRun, trading_position: MetalsTradingPosition, warehouse_receipt: MetalWarehouseReceipt, output_metal_ton: float}
     */
    public function handle(
        string $siteId,
        float $oreInputTon = 1000.0,
        float $oreGradePct = 1.85,
        float $recoveryRatePct = 85.0,
        float $tradingTonnage = 15.0,
        int $entryPriceUsd = 18500,
        int $currentMarkPriceUsd = 19200,
        string $warehouseId = 'WMS-VAL-01'
    ): array {
        return DB::transaction(function () use (
            $siteId,
            $oreInputTon,
            $oreGradePct,
            $recoveryRatePct,
            $tradingTonnage,
            $entryPriceUsd,
            $currentMarkPriceUsd,
            $warehouseId
        ) {
            // 121.1 Smelter recovery calculation respecting conservation of mass
            $outputMetalTon = round($oreInputTon * ($oreGradePct / 100.0) * ($recoveryRatePct / 100.0), 3);
            if ($outputMetalTon > $oreInputTon) {
                throw new RuntimeException("Mass conservation breach: Recovered metal ({$outputMetalTon} tons) cannot exceed input ore ({$oreInputTon} tons)");
            }

            $slagTonnage = round($oreInputTon - $outputMetalTon, 2);
            $byproductRevenueIdr = (int) round($slagTonnage * 150000);

            $runCount = SmelterRun::where('site_id', $siteId)->count() + 1;
            $runCode = sprintf('SML-%04d', $runCount);

            $smelterRun = SmelterRun::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'run_code' => $runCode,
                'output_commodity' => 'NICKEL_PIG_IRON',
                'input_ore_tonnage' => $oreInputTon,
                'input_grade_pct' => $oreGradePct,
                'output_metal_tonnage' => $outputMetalTon,
                'recovery_rate_pct' => $recoveryRatePct,
                'byproduct_slag_tonnage' => $slagTonnage,
                'byproduct_revenue_idr' => $byproductRevenueIdr,
                'energy_kwh_per_ton' => 3800.0,
                'status' => 'COMPLETED',
            ]);

            // 121.3 Metals Trading Desk MTM Position
            $unrealizedPnlUsd = ($currentMarkPriceUsd - $entryPriceUsd) * $tradingTonnage;
            $unrealizedPnlMinor = (int) round($unrealizedPnlUsd * 100);

            $tradingPosition = MetalsTradingPosition::create([
                'id' => (string) Str::uuid(),
                'desk_code' => 'DSK-LME-NI-01',
                'commodity' => 'NICKEL_CATHODE',
                'position_type' => 'LONG_OFFTAKE',
                'tonnage' => $tradingTonnage,
                'entry_price_usd' => (string) $entryPriceUsd,
                'current_mark_price_usd' => (string) $currentMarkPriceUsd,
                'unrealized_pnl_minor' => $unrealizedPnlMinor,
                'status' => 'OPEN',
            ]);

            // 121.4 Digital Warehouse Receipt for Financing Collateral
            $receiptNumber = 'MWR-'.strtoupper(bin2hex(random_bytes(4)));
            $receiptHash = hash('sha256', "MWR-{$warehouseId}-{$outputMetalTon}-".date('YmdHis'));

            $warehouseReceipt = MetalWarehouseReceipt::create([
                'id' => (string) Str::uuid(),
                'receipt_number' => $receiptNumber,
                'warehouse_id' => $warehouseId,
                'commodity' => 'NICKEL_PIG_IRON',
                'stored_tonnage' => $outputMetalTon,
                'receipt_hash' => $receiptHash,
                'is_collateralized' => true,
                'collateral_facility_id' => 'FAC-SCF-BNI-01',
                'financing_amount_minor' => (int) round($outputMetalTon * 15000000000),
                'status' => 'ISSUED',
            ]);

            $this->audit(
                action: 'mining.smelter_trading.settled',
                auditable: $smelterRun,
                oldValues: null,
                newValues: [
                    'run_code' => $runCode,
                    'output_metal_ton' => $outputMetalTon,
                    'unrealized_pnl_minor' => $unrealizedPnlMinor,
                    'receipt_number' => $receiptNumber,
                ],
                correlationId: "min_sml_{$runCode}",
                impactType: 'smelter_metals_trading'
            );

            return [
                'smelter_run' => $smelterRun,
                'trading_position' => $tradingPosition,
                'warehouse_receipt' => $warehouseReceipt,
                'output_metal_ton' => $outputMetalTon,
            ];
        });
    }
}
