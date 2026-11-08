<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

final class RoomBlockAllotmentReleasedEvent
{
    /**
     * @param  array<string, int>  $inventoryByRoomType
     */
    public function __construct(
        public readonly string $allotmentId,
        public readonly string $contractNumber,
        public readonly int $releasedRooms,
        public readonly string $releaseStage,
        public readonly array $inventoryByRoomType = []
    ) {}
}
