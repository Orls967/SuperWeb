<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Listeners;

use Modules\Pricing\Application\Services\PricingService;
use Modules\Store\Contracts\StoreOrderReaderInterface;
use Modules\Store\Domain\Events\OrderPaid;

/**
 * 44.6 Integrai Store: kunci harga per baris order saat dibayar.
 * Idempoten (price lock replay aman); tanpa import Domain Pricing lain.
 */
class PostSalePriceLockListener
{
    public function __construct(private readonly PricingService $pricing) {}

    public function handle(OrderPaid $event): void
    {
        $lines = app(StoreOrderReaderInterface::class)->getOrderItemsWithProduct($event->orderId);

        foreach ($lines as $line) {
            if ($line->sku === null || $line->sku === '') {
                continue;
            }

            try {
                $quote = $this->pricing->quote(
                    sku: (string) $line->sku,
                    qty: (float) $line->qty,
                    channel: 'retail',
                );
                $price = $quote['price_idr'];
                $source = $quote['source_kind'];
                $waterfall = $quote['waterfall'];
            } catch (\InvalidArgumentException) {
                // SKU tanpa price list → pakai snapshot harga order apa adanya.
                $price = (int) $line->price_snapshot;
                $source = 'order_snapshot';
                $waterfall = [];
            }

            $this->pricing->lockPrice(
                subjectType: 'store_order',
                subjectId: (string) $event->orderId,
                sku: (string) $line->sku,
                qty: (float) $line->qty,
                listPrice: $price,
                appliedPrice: $price,
                discount: 0,
                list: null,
                sourceKind: $source,
                sourceRuleId: null,
                waterfall: $waterfall,
                reason: null,
            );
        }
    }
}
