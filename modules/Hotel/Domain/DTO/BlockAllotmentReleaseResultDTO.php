<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\DTO;

final class BlockAllotmentReleaseResultDTO
{
    /**
     * @param  array<string, int>  $releasedInventoryByType
     * @param  array<int, string>  $auditLog
     */
    public function __construct(
        public readonly string $allotmentId,
        public readonly string $contractNumber,
        public readonly int $initialAllocatedRooms,
        public readonly int $pickedUpRooms,
        public readonly int $releasedRooms,
        public readonly int $retainedRooms,
        public readonly string $releaseStage, // H-30, H-14, H-7, final_wash
        public readonly array $releasedInventoryByType,
        public readonly array $auditLog
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allotment_id' => $this->allotmentId,
            'contract_number' => $this->contractNumber,
            'initial_allocated_rooms' => $this->initialAllocatedRooms,
            'picked_up_rooms' => $this->pickedUpRooms,
            'released_rooms' => $this->releasedRooms,
            'retained_rooms' => $this->retainedRooms,
            'release_stage' => $this->releaseStage,
            'released_inventory_by_type' => $this->releasedInventoryByType,
            'audit_log' => $this->auditLog,
        ];
    }
}
