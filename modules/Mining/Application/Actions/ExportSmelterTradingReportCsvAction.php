<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Domain\Models\MiningMetalsTradingContract;
use Modules\Mining\Domain\Models\MiningMetalsWarehouseReceipt;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;

final class ExportSmelterTradingReportCsvAction
{
    /**
     * @return array{
     *     filename: string,
     *     content: string,
     *     rows_count: int
     * }
     */
    public function execute(): array
    {
        $runs = MiningSmelterFurnaceRun::with(['furnace', 'energyEmissions', 'qualityAssays', 'byproducts'])->get();
        $receipts = MiningMetalsWarehouseReceipt::all();
        $contracts = MiningMetalsTradingContract::with('positions')->get();

        $lines = [];
        $lines[] = 'TYPE,CODE,REFERENCE,PRIMARY_METRIC,SECONDARY_METRIC,STATUS,TIMESTAMP';

        foreach ($runs as $run) {
            $lines[] = sprintf(
                'FURNACE_RUN,%s,%s,%.2f DMT,%.2f Ton Metal,%s,%s',
                $run->run_code,
                $run->furnace?->furnace_code ?? 'FURNACE',
                $run->ore_feed_dmt,
                $run->metal_output_tons,
                $run->status,
                $run->created_at?->toIso8601String() ?? ''
            );
        }

        foreach ($receipts as $receipt) {
            $lines[] = sprintf(
                'WAREHOUSE_RECEIPT,%s,%s,%.2f MT,Rp %d,%s,%s',
                $receipt->receipt_number,
                $receipt->warehouse_code,
                $receipt->certified_weight_metric_tons,
                $receipt->max_financing_limit_idr,
                $receipt->status,
                $receipt->created_at?->toIso8601String() ?? ''
            );
        }

        foreach ($contracts as $contract) {
            $lines[] = sprintf(
                'TRADING_CONTRACT,%s,%s,%.2f MT,$ %d/t,%s,%s',
                $contract->contract_number,
                $contract->counterparty_name,
                $contract->contract_quantity_metric_tons,
                (int) round($contract->fixed_price_usd_cents_per_ton / 100),
                $contract->status,
                $contract->created_at?->toIso8601String() ?? ''
            );
        }

        $content = implode("\n", $lines);
        $filename = 'mining_smelter_trading_report_'.now()->format('Ymd_His').'.csv';

        return [
            'filename' => $filename,
            'content' => $content,
            'rows_count' => count($lines) - 1,
        ];
    }
}
