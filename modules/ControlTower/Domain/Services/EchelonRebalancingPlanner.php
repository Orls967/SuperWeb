<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Services;

use Modules\ControlTower\Domain\ValueObjects\EchelonPosition;

final class EchelonRebalancingPlanner
{
    /**
     * @param  list<EchelonPosition>  $positions
     * @return list<array{from_node: string, to_node: string, transfer_qty: int}>
     */
    public function plan(array $positions): array
    {
        $surpluses = [];
        $deficits = [];

        foreach ($positions as $pos) {
            $net = $pos->netAvailableQty();
            if ($net > $pos->safetyStockQty) {
                $surpluses[] = ['node' => $pos->echelonNode, 'qty' => $net - $pos->safetyStockQty];
            } elseif ($net < $pos->safetyStockQty) {
                $deficits[] = ['node' => $pos->echelonNode, 'qty' => $pos->safetyStockQty - $net];
            }
        }

        $transfers = [];
        foreach ($deficits as &$def) {
            foreach ($surpluses as &$sur) {
                if ($def['qty'] <= 0 || $sur['qty'] <= 0) {
                    continue;
                }
                $move = min($def['qty'], $sur['qty']);
                $transfers[] = [
                    'from_node' => $sur['node'],
                    'to_node' => $def['node'],
                    'transfer_qty' => $move,
                ];
                $def['qty'] -= $move;
                $sur['qty'] -= $move;
            }
        }

        return $transfers;
    }
}
