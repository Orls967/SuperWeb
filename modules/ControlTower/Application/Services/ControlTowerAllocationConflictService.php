<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\OrderPromise;

final class ControlTowerAllocationConflictService
{
    /**
     * @return list<array{item_code: string, promised_atp: int, available_supply: int, excess_qty: int}>
     */
    public function detectOverAllocatedItems(): array
    {
        $conflicts = [];
        $promised = OrderPromise::query()
            ->selectRaw('item_code, SUM(atp_confirmed_qty) as total_atp')
            ->groupBy('item_code')
            ->pluck('total_atp', 'item_code');

        foreach ($promised as $itemCode => $totalAtp) {
            $supply = (int) EchelonStock::query()
                ->where('item_code', $itemCode)
                ->selectRaw('COALESCE(SUM(on_hand_qty + in_transit_qty), 0) as total_supply')
                ->value('total_supply');
            if ((int) $totalAtp > $supply) {
                $conflicts[] = [
                    'item_code' => (string) $itemCode,
                    'promised_atp' => (int) $totalAtp,
                    'available_supply' => $supply,
                    'excess_qty' => (int) $totalAtp - $supply,
                ];
            }
        }

        return $conflicts;
    }
}
