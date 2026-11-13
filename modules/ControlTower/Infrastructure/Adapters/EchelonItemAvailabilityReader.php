<?php

declare(strict_types=1);

namespace Modules\ControlTower\Infrastructure\Adapters;

use Modules\ControlTower\Contracts\ItemAvailabilityReader;
use Modules\ControlTower\Domain\Models\EchelonStock;

/**
 * Fallback pembaca posisi stok berbasis tabel EchelonStock ControlTower ketika adapter WMS belum terikat.
 */
final class EchelonItemAvailabilityReader implements ItemAvailabilityReader
{
    public function positionFor(string $itemCode, string $asOfDate): array
    {
        $rows = EchelonStock::query()->where('item_code', $itemCode)->get();
        $onHand = (int) $rows->sum('on_hand_qty');
        $reserved = (int) $rows->sum('reserved_qty');
        $available = max(0, $onHand - $reserved);

        return [
            'on_hand' => $onHand,
            'available' => $available,
            'quarantine' => 0,
            'hold' => $reserved,
            'oldest_lot_age_days' => 0,
        ];
    }
}
