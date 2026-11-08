<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Domain\Models\HtlBanquetOrder;
use Modules\Hotel\Domain\Services\HotelBanquetRecipeBomService;

final class HotelSyncBanquetBomProcurementCommand extends Command
{
    protected $signature = 'hotel:sync-banquet-bom {--beo= : Optional specific BEO order number}';

    protected $description = 'Synchronize banquet recipe Bill of Materials (BOM) with procurement inventory requisitions';

    public function handle(HotelBanquetRecipeBomService $bomService): int
    {
        $this->info('Analyzing active BEO banquet production orders for procurement BOM explosion...');

        $query = HtlBanquetOrder::whereIn('status', ['confirmed', 'draft']);
        if ($this->option('beo')) {
            $query->where('beo_number', $this->option('beo'));
        }

        $orders = $query->limit(20)->get();
        if ($orders->isEmpty()) {
            $this->warn('No active BEO production orders found to sync.');

            return self::SUCCESS;
        }

        $headers = ['BEO Number', 'Event Date', 'Pax', 'Total Prep Pax', 'Estimated BOM Cost'];
        $rows = [];
        $totalCost = 0.0;

        foreach ($orders as $order) {
            $prep = $bomService->prepareProductionSheet($order->id);
            $cost = (float) ($prep['financial_summary']['total_recipe_cost'] ?? 0.0);
            $totalCost += $cost;

            $rows[] = [
                $order->beo_number,
                $order->event_date,
                $order->guaranteed_pax,
                $order->totalPreparedPax(),
                'IDR '.number_format($cost, 2),
            ];
        }

        $this->table($headers, $rows);
        $this->info('Total procurement requisition requirements generated: IDR '.number_format($totalCost, 2));

        return self::SUCCESS;
    }
}
