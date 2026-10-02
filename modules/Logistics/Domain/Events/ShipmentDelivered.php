<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Events;

use Modules\Logistics\Domain\Models\Shipment;

final class ShipmentDelivered
{
    public function __construct(
        public readonly Shipment $shipment
    ) {}
}
