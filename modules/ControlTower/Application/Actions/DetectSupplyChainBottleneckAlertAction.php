<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Actions;

use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\EchelonStock;

class DetectSupplyChainBottleneckAlertAction
{
    /**
     * @return array<int, DisruptionAlert>
     */
    public function execute(?string $itemCode = null): array
    {
        $query = EchelonStock::query();
        if ($itemCode) {
            $query->where('item_code', $itemCode);
        }

        $stocks = $query->get();
        $generatedAlerts = [];

        foreach ($stocks as $stock) {
            $effectiveStock = ($stock->on_hand_qty + $stock->in_transit_qty) - $stock->reserved_qty;

            if ($effectiveStock < $stock->safety_stock_qty) {
                $severity = 'MEDIUM';
                if ($effectiveStock <= 0) {
                    $severity = 'CRITICAL';
                } elseif ($effectiveStock < (int) ($stock->safety_stock_qty / 2)) {
                    $severity = 'HIGH';
                }

                $alertCode = 'ALT-'.strtoupper($stock->item_code).'-'.strtoupper($stock->echelon_node);
                $title = "Stockout Risk on {$stock->item_code} at {$stock->echelon_node}";
                $desc = "Effective stock ({$effectiveStock}) is below safety threshold ({$stock->safety_stock_qty}).";

                $alert = DisruptionAlert::updateOrCreate(
                    ['alert_code' => $alertCode],
                    [
                        'severity' => $severity,
                        'category' => 'SUPPLY_CHAIN_BOTTLENECK',
                        'title' => $title,
                        'description' => $desc,
                        'affected_orders_count' => max(1, (int) round(($stock->safety_stock_qty - $effectiveStock) / 10)),
                        'status' => 'active',
                    ]
                );

                $generatedAlerts[] = $alert;
            }
        }

        return $generatedAlerts;
    }
}
