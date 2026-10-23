<?php

declare(strict_types=1);

namespace Modules\Ret\Console\Commands;

use Illuminate\Console\Command;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetFulfillmentSplit;
use Modules\Ret\Domain\Models\RetInventoryItem;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Ret\Domain\Models\RetPackagingDeposit;
use Modules\Ret\Domain\Models\RetQuickCommerceOrder;

class RetAuditCommand extends Command
{
    protected $signature = 'ret:audit';

    protected $description = 'Audit operasional ritel & omnichannel: integritas stok, harga MAP, pemenuhan dark store, dan deposit kemasan';

    public function handle(): int
    {
        $this->info('Starting Omnichannel Retail & Quick Commerce Operations Audit (ret:audit)...');

        $channelCount = RetChannel::count();
        $itemCount = RetInventoryItem::count();
        $orderCount = RetOrder::count();
        $splitCount = RetFulfillmentSplit::count();
        $qcOrderCount = RetQuickCommerceOrder::count();
        $depositCount = RetPackagingDeposit::count();

        $discrepancies = 0;

        // Check negative stock items
        $negativeStock = RetInventoryItem::where('stock_available', '<', 0)->count();
        if ($negativeStock > 0) {
            $this->warn("Found {$negativeStock} inventory items with negative available stock.");
            $discrepancies += $negativeStock;
        }

        // Check non-positive pricing
        $invalidPricing = RetInventoryItem::where('map_price_minor', '<=', 0)->count();
        if ($invalidPricing > 0) {
            $this->warn("Found {$invalidPricing} inventory items with zero or negative price.");
            $discrepancies += $invalidPricing;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Retail Channels', $channelCount],
                ['Inventory SKU Items', $itemCount],
                ['Omnichannel Orders', $orderCount],
                ['Fulfillment Splits', $splitCount],
                ['Dark Store QC Orders', $qcOrderCount],
                ['Packaging Deposits', $depositCount],
                ['Integrity Discrepancies', $discrepancies],
            ]
        );

        if ($discrepancies > 0) {
            $this->error("Retail audit FAILED with {$discrepancies} inventory discrepancies detected!");

            return self::FAILURE;
        }

        $this->info('Retail audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
