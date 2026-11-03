<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use DomainException;
use Modules\Port\Domain\Models\YardBlock;
use Modules\Port\Domain\Models\YardSlot;

class AssignYardSlotAction
{
    /**
     * Menempatkan atau memindahkan kontainer ke koordinat slot yard tertentu.
     */
    public function execute(string $containerNo, int $yardBlockId, string $slotCoordinate): YardSlot
    {
        $block = YardBlock::findOrFail($yardBlockId);

        // Cek kapasitas blok
        $currentOccupancy = YardSlot::where('yard_block_id', $block->id)
            ->where('status', 'IN_YARD')
            ->count();

        if ($currentOccupancy >= $block->capacity_teu) {
            throw new DomainException("Kapasitas blok penumpukan {$block->code} telah penuh ({$block->capacity_teu} TEU).");
        }

        $slot = YardSlot::where('container_no', $containerNo)
            ->where('status', 'IN_YARD')
            ->latest('id')
            ->first();

        if ($slot === null) {
            $slot = YardSlot::create([
                'yard_block_id' => $block->id,
                'container_no' => $containerNo,
                'slot_coordinate' => $slotCoordinate,
                'status' => 'IN_YARD',
                'gate_in_at' => now(),
            ]);
        } else {
            $slot->update([
                'yard_block_id' => $block->id,
                'slot_coordinate' => $slotCoordinate,
            ]);
        }

        return $slot;
    }
}
