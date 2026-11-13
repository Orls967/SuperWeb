<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Domain\Algorithms\PutawaySlotter;

/**
 * X41-4: Perencana simulasi what-if re-slotting ABC berbasis PutawaySlotter (H1).
 */
final class SlottingScenarioPlanner
{
    public function __construct(
        private readonly PutawaySlotter $slotter,
    ) {}

    /**
     * Mensimulasikan relokasi batch SKU ke bin target dan menghitung estimasi penghematan jarak tempuh.
     *
     * @param  list<array{sku: string, abcClass: string, qty: int, currentDistance: int}>  $items
     * @param  list<array{id: string|int, distance: int, freeCapacity: int}>  $availableBins
     * @return array{
     *     moves: list<array{sku: string, allocations: list<array{bin_id: string, qty: int}>, distance_saved: int}>,
     *     total_distance_saved: int
     * }
     */
    public function simulateRelocation(array $items, array $availableBins): array
    {
        $binPool = [];
        foreach ($availableBins as $bin) {
            $binPool[(string) $bin['id']] = [
                'id' => (string) $bin['id'],
                'distance' => (int) $bin['distance'],
                'freeCapacity' => (int) $bin['freeCapacity'],
            ];
        }

        $moves = [];
        $totalSaved = 0;

        foreach ($items as $item) {
            $plan = $this->slotter->plan((int) $item['qty'], (string) $item['abcClass'], array_values($binPool));
            $savedForItem = 0;

            foreach ($plan['allocations'] as $alloc) {
                $binId = $alloc['bin_id'];
                $targetDistance = $binPool[$binId]['distance'];
                $binPool[$binId]['freeCapacity'] -= $alloc['qty'];
                $savedForItem += max(0, ((int) $item['currentDistance'] - $targetDistance) * $alloc['qty']);
            }

            $totalSaved += $savedForItem;
            $moves[] = [
                'sku' => (string) $item['sku'],
                'allocations' => $plan['allocations'],
                'distance_saved' => $savedForItem,
            ];
        }

        return [
            'moves' => $moves,
            'total_distance_saved' => $totalSaved,
        ];
    }
}
