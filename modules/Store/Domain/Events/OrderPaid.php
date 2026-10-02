<?php

declare(strict_types=1);

namespace Modules\Store\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a Store order is paid and needs shipping.
 *
 * Logistics listens to this event to auto-create a shipment.
 */
class OrderPaid
{
    use Dispatchable;

    public function __construct(
        public readonly int $orderId,
        public readonly int $userId,
        public readonly int $amountIdr,
    ) {}
}
