<?php

declare(strict_types=1);

namespace Modules\Ret\Console\Commands;

use Illuminate\Console\Command;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Ret\Domain\Models\RetCashbackTransaction;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetFulfillmentSplit;
use Modules\Ret\Domain\Models\RetInventoryItem;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Ret\Domain\Models\RetPackagingDeposit;
use Modules\Ret\Domain\Models\RetQuickCommerceOrder;
use Modules\Ret\Domain\Models\RetSellerSettlement;

class RetAuditCommand extends Command
{
    protected $signature = 'ret:audit';

    protected $description = 'Audit operasional ritel & omnichannel: integritas stok, harga MAP, pemenuhan dark store, dan rekonsiliasi ledger dua-sumber';

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

        // 1. Check legacy negative stock items (supports RetAuditCorruptionFixture)
        $negativeStock = RetInventoryItem::where('stock_available', '<', 0)->count();
        if ($negativeStock > 0) {
            $this->warn("Found {$negativeStock} inventory items with negative available stock.");
            $discrepancies += $negativeStock;
        }

        // 2. Check legacy non-positive pricing
        $invalidPricing = RetInventoryItem::where('map_price_minor', '<=', 0)->count();
        if ($invalidPricing > 0) {
            $this->warn("Found {$invalidPricing} inventory items with zero or negative price.");
            $discrepancies += $invalidPricing;
        }

        // 3. Two-source Seller AP Reconciliation: Settlements vs Ledger Postings
        $expectedAp = (int) RetSellerSettlement::where('status', 'SETTLED')
            ->sum('net_seller_payable_minor');

        $actualAp = (int) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:seller_payable:IDR'))
            ->sum('amount');

        if ($expectedAp !== $actualAp) {
            $this->error("Tampered seller AP balance: expected {$expectedAp}, recorded {$actualAp}.");
            $discrepancies++;
        }

        // 4. Two-source Cashback Liability Reconciliation: Active Tranches vs Ledger Postings
        $earnedCashback = (int) RetCashbackTransaction::where('type', 'EARNED')->sum('amount_minor');
        $spentCashback = (int) RetCashbackTransaction::whereIn('type', ['REDEEMED', 'EXPIRED'])->sum('amount_minor');
        $activeCashback = max(0, $earnedCashback - $spentCashback);

        $ledgerCashback = (int) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:cashback_liability:IDR'))
            ->sum('amount');

        if ($activeCashback !== $ledgerCashback) {
            $this->error("Tampered cashback liability: operational {$activeCashback}, ledger {$ledgerCashback}.");
            $discrepancies++;
        }

        // 5. Two-source Packaging Deposit Liability Reconciliation: Held Deposits vs Ledger Postings
        $heldDeposits = (int) RetPackagingDeposit::where('status', 'HELD')->sum('deposit_amount_minor');
        $ledgerDeposits = (int) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:deposit_liability:IDR'))
            ->sum('amount');

        if ($heldDeposits !== $ledgerDeposits) {
            $this->error("Tampered packaging deposit liability: operational {$heldDeposits}, ledger {$ledgerDeposits}.");
            $discrepancies++;
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
