<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Listeners;

use Modules\Manufacturing\Application\Services\CostingService;
use Modules\Store\Domain\Events\OrderPaid;

/**
 * 38.5 COGS saat barang jadi terjual: dengarkan event Store `OrderPaid`
 * (dipublish `Order::onPaymentCaptured`) dan posting HPP per baris item
 * yang SKU-nya cocok dengan kode material pabrik. Idempoten per
 * (order, item) via key ledger `mfg:cogs:{order}:{item}`.
 */
class PostSaleCogsListener
{
    public function __construct(private readonly CostingService $costing) {}

    public function handle(OrderPaid $event): void
    {
        $this->costing->postOrderCogs($event->orderId);
    }
}
