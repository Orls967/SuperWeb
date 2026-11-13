<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Services;

use Modules\ControlTower\Domain\ValueObjects\EchelonPosition;

final class MultiEchelonVisibilityAggregator
{
    /**
     * @param  list<EchelonPosition>  $positions
     * @return array{
     *     total_on_hand: int,
     *     total_in_transit: int,
     *     total_reserved: int,
     *     total_net_available: int,
     *     nodes_below_safety: list<string>
     * }
     */
    public function aggregate(array $positions): array
    {
        $onHand = 0;
        $inTransit = 0;
        $reserved = 0;
        $net = 0;
        $below = [];

        foreach ($positions as $pos) {
            $onHand += $pos->onHandQty;
            $inTransit += $pos->inTransitQty;
            $reserved += $pos->reservedQty;
            $net += $pos->netAvailableQty();
            if ($pos->isBelowSafetyStock()) {
                $below[] = $pos->echelonNode;
            }
        }

        return [
            'total_on_hand' => $onHand,
            'total_in_transit' => $inTransit,
            'total_reserved' => $reserved,
            'total_net_available' => $net,
            'nodes_below_safety' => $below,
        ];
    }
}
