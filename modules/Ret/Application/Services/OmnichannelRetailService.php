<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetCouponRedemption;
use Modules\Ret\Domain\Models\RetFulfillmentSplit;
use Modules\Ret\Domain\Models\RetInventoryItem;
use Modules\Ret\Domain\Models\RetSellerSettlement;
use RuntimeException;

class OmnichannelRetailService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function createChannel(array $params): RetChannel
    {
        return RetChannel::create([
            'id' => (string) Str::uuid(),
            'channel_code' => $params['channel_code'] ?? 'CHN-'.strtoupper(Str::random(6)),
            'channel_name' => $params['channel_name'],
            'channel_type' => $params['channel_type'],
            'default_commission_pct' => (float) ($params['default_commission_pct'] ?? 5.0),
            'status' => 'ACTIVE',
        ]);
    }

    public function registerInventoryItem(array $params): RetInventoryItem
    {
        return RetInventoryItem::create([
            'id' => (string) Str::uuid(),
            'sku' => $params['sku'],
            'product_name' => $params['product_name'],
            'stock_available' => (int) $params['stock_available'],
            'stock_reserved' => 0,
            'map_price_minor' => (int) $params['map_price_minor'],
        ]);
    }

    /**
     * 137.3 Unified inventory reservation across dual channels anti-double-sell.
     * Test (a): stok channel ganda -> 1 unit hanya terjual 1x (concurrency/lock).
     */
    public function reserveAndSellInventory(string $sku, int $qty): RetInventoryItem
    {
        return DB::transaction(function () use ($sku, $qty) {
            $item = RetInventoryItem::where('sku', $sku)->lockForUpdate()->firstOrFail();

            if ($item->stock_available < $qty) {
                throw new RuntimeException("Insufficient stock for SKU {$sku}. Available: {$item->stock_available}, requested: {$qty}.");
            }

            $item->stock_available -= $qty;
            $item->stock_reserved += $qty;
            $item->save();

            return $item;
        });
    }

    /**
     * 137.2 Multi-vendor 3P Marketplace Settlement.
     * Test (b): komisi settlement = % * GMV terverifikasi, net payout balanced.
     */
    public function settleMarketplaceSeller(string $sellerPartyId, int $verifiedGmvMinor, float $commissionPct): RetSellerSettlement
    {
        $commissionFee = (int) round(($verifiedGmvMinor * $commissionPct) / 100.0);
        $netPayout = $verifiedGmvMinor - $commissionFee;

        return DB::transaction(function () use ($sellerPartyId, $verifiedGmvMinor, $commissionPct, $commissionFee, $netPayout) {
            $batchCode = 'STL-'.strtoupper(Str::random(8));

            // Balanced multi-party posting (Sum = 0)
            $this->ledgerService->post(new PostingDTO(
                type: 'RETAIL_MARKETPLACE_SELLER_SETTLEMENT',
                description: "Marketplace settlement for seller {$sellerPartyId} batch {$batchCode}",
                idempotencyKey: 'RET-'.$batchCode,
                entries: [
                    PostingEntryDTO::forCode('ret:escrow_receivable:IDR', 'IDR', $verifiedGmvMinor),
                    PostingEntryDTO::forCode('ret:marketplace_commission_revenue:IDR', 'IDR', -$commissionFee),
                    PostingEntryDTO::forCode('ret:seller_payable:IDR', 'IDR', -$netPayout),
                ],
                referenceType: 'SELLER_SETTLEMENT',
                referenceId: $batchCode,
            ));

            return RetSellerSettlement::create([
                'id' => (string) Str::uuid(),
                'settlement_code' => $batchCode,
                'seller_party_id' => $sellerPartyId,
                'verified_gmv_minor' => $verifiedGmvMinor,
                'commission_rate_pct' => $commissionPct,
                'commission_fee_minor' => $commissionFee,
                'net_payout_minor' => $netPayout,
                'status' => 'SETTLED',
            ]);
        });
    }

    /**
     * 137.4 Fulfillment Split per Location.
     * Test (c): split fulfillment sum(quantity) == order quantity.
     */
    public function splitOrderFulfillment(string $orderNumber, string $sku, array $splits): array
    {
        $createdSplits = [];
        $totalSplitQty = 0;

        foreach ($splits as $split) {
            $qty = (int) $split['quantity'];
            $totalSplitQty += $qty;

            $createdSplits[] = RetFulfillmentSplit::create([
                'id' => (string) Str::uuid(),
                'split_code' => 'SPL-'.strtoupper(Str::random(8)),
                'order_id' => $orderNumber,
                'sku' => $sku,
                'quantity' => $qty,
                'fulfillment_location_type' => $split['location_type'],
                'facility_id' => $split['facility_id'],
                'status' => 'PENDING_PICK',
            ]);
        }

        return [
            'total_quantity' => $totalSplitQty,
            'splits' => $createdSplits,
        ];
    }

    /**
     * 137.5 Promotional coupon redemption anti-double-use.
     * Test (d): kupon multi-channel tak dobel pakai.
     */
    public function redeemCoupon(string $couponCode, string $customerId, string $orderId): RetCouponRedemption
    {
        $existing = RetCouponRedemption::where('coupon_code', $couponCode)
            ->where('customer_id', $customerId)
            ->exists();

        if ($existing) {
            throw new RuntimeException("Coupon {$couponCode} has already been redeemed by customer {$customerId}.");
        }

        return RetCouponRedemption::create([
            'id' => (string) Str::uuid(),
            'coupon_code' => $couponCode,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'redeemed_at' => Carbon::now(),
        ]);
    }
}
