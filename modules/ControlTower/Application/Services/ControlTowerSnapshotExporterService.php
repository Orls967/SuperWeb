<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Modules\ControlTower\Domain\Models\EchelonStock;

final class ControlTowerSnapshotExporterService
{
    /**
     * @return list<array<string, int|string>>
     */
    public function exportEchelonRows(): array
    {
        return EchelonStock::query()
            ->orderBy('item_code')
            ->orderBy('echelon_node')
            ->get()
            ->map(static fn (EchelonStock $row): array => [
                'item_code' => $row->item_code,
                'echelon_node' => $row->echelon_node,
                'on_hand_qty' => (int) $row->on_hand_qty,
                'in_transit_qty' => (int) $row->in_transit_qty,
                'reserved_qty' => (int) $row->reserved_qty,
                'safety_stock_qty' => (int) $row->safety_stock_qty,
                'abc_class' => $row->abc_class,
                'xyz_class' => $row->xyz_class,
            ])
            ->all();
    }
}
