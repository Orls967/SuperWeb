<?php

declare(strict_types=1);

namespace Modules\ControlTower\Infrastructure\Adapters;

use Modules\ControlTower\Contracts\ControlTowerVisibilityReader;
use Modules\ControlTower\Domain\Models\EchelonStock;

final class ControlTowerVisibilityReaderAdapter implements ControlTowerVisibilityReader
{
    public function getItemVisibility(string $itemCode): array
    {
        $rows = EchelonStock::query()->where('item_code', $itemCode)->get();
        $onHand = (int) $rows->sum('on_hand_qty');
        $inTransit = (int) $rows->sum('in_transit_qty');
        $reserved = (int) $rows->sum('reserved_qty');

        return [
            'item_code' => $itemCode,
            'total_on_hand' => $onHand,
            'total_in_transit' => $inTransit,
            'total_reserved' => $reserved,
            'net_available' => max(0, $onHand + $inTransit - $reserved),
        ];
    }

    public function getNodeBreakdown(string $itemCode): array
    {
        return EchelonStock::query()
            ->where('item_code', $itemCode)
            ->pluck('on_hand_qty', 'echelon_node')
            ->map(static fn ($qty): int => (int) $qty)
            ->all();
    }
}
