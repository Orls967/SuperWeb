<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Hotel\Domain\Events\RoomBlockAllotmentReleasedEvent;

final class SyncAllotmentToCrsInventoryListener
{
    public function handle(RoomBlockAllotmentReleasedEvent $event): void
    {
        Log::info('Syncing released room block allotment to CRS inventory', [
            'allotment_id' => $event->allotmentId,
            'contract_number' => $event->contractNumber,
            'released_rooms' => $event->releasedRooms,
            'stage' => $event->releaseStage,
            'inventory' => $event->inventoryByRoomType,
        ]);
        // Release lock on public room inventory in CRS/PMS
    }
}
