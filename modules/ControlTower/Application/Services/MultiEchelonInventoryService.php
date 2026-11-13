<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Modules\ControlTower\Contracts\ControlTowerVisibilityReader;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Services\EchelonRebalancingPlanner;
use Modules\ControlTower\Domain\Services\MultiEchelonVisibilityAggregator;
use Modules\ControlTower\Domain\ValueObjects\EchelonPosition;

final class MultiEchelonInventoryService
{
    public function __construct(
        private readonly ControlTowerVisibilityReader $visibilityReader,
        private readonly MultiEchelonVisibilityAggregator $aggregator,
        private readonly EchelonRebalancingPlanner $rebalancer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inspectItemNetwork(string $itemCode): array
    {
        $rows = EchelonStock::query()->where('item_code', $itemCode)->get();
        $positions = $rows->map(static fn (EchelonStock $s): EchelonPosition => new EchelonPosition(
            itemCode: $s->item_code,
            echelonNode: $s->echelon_node,
            onHandQty: (int) $s->on_hand_qty,
            inTransitQty: (int) $s->in_transit_qty,
            reservedQty: (int) $s->reserved_qty,
            safetyStockQty: (int) $s->safety_stock_qty,
        ))->all();

        return [
            'summary' => $this->visibilityReader->getItemVisibility($itemCode),
            'aggregation' => $this->aggregator->aggregate($positions),
            'rebalancing_plan' => $this->rebalancer->plan($positions),
        ];
    }
}
