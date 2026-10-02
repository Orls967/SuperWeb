<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Listeners;

use Modules\Logistics\Application\Actions\RecognizeFreightRevenueAction;
use Modules\Logistics\Domain\Events\ShipmentDelivered;

class RecognizeFreightRevenueOnDelivery
{
    public function __construct(
        private readonly RecognizeFreightRevenueAction $action
    ) {}

    public function handle(ShipmentDelivered $event): void
    {
        $this->action->execute($event->shipment);
    }
}
