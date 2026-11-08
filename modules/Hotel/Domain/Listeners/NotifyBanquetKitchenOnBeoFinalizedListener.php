<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Hotel\Domain\Events\BanquetBeoFinalizedEvent;

final class NotifyBanquetKitchenOnBeoFinalizedListener
{
    public function handle(BanquetBeoFinalizedEvent $event): void
    {
        Log::info('Dispatched BEO production sheet to Executive Banquet Chef', [
            'beo_order_id' => $event->beoOrderId,
            'beo_number' => $event->beoNumber,
            'event_date' => $event->eventDate,
            'total_pax' => $event->totalPreparedPax,
            'cost' => $event->totalFoodBeverageCost,
        ]);
        // Trigger procurement requisition if inventory buffer is insufficient
    }
}
