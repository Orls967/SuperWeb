<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Illuminate\Support\Str;
use Modules\Ret\Domain\Models\RetFulfillmentSplit;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\Numerics\FulfillmentPlanner;
use Modules\Shared\Domain\Numerics\FulfillmentPlannerVerifier;

class PlanOmnichannelFulfillmentAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{order_id: string, splits: list<RetFulfillmentSplit>, certificate: array<string, mixed>}
     */
    public function execute(array $params): array
    {
        $order = RetOrder::findOrFail($params['order_id']);
        $items = (array) $params['items'];

        // Build order lines for FulfillmentPlanner
        $orderLines = [];
        foreach ($items as $it) {
            $sku = (string) $it['sku'];
            $qty = (int) $it['quantity'];
            $orderLines[$sku] = $qty;
        }

        // Available fulfillment nodes (Dark Store, Central Warehouse, Storefront)
        $nodes = [
            'NODE-DARK-STORE-01' => [
                'cost' => 15_000,
                'stock' => [
                    'SKU-FRESH-MILK' => 50,
                    'SKU-ICE-CREAM' => 30,
                ],
            ],
            'NODE-FDC-CENTRAL-01' => [
                'cost' => 25_000,
                'stock' => [
                    'SKU-FRESH-MILK' => 100,
                    'SKU-ICE-CREAM' => 100,
                ],
            ],
        ];

        // Optimize using B0″.9 FulfillmentPlanner
        $planResult = FulfillmentPlanner::plan($orderLines, $nodes, FulfillmentPlanner::MODE_COST);

        // Verify with FulfillmentPlannerVerifier (PR2 principle)
        $isValid = FulfillmentPlannerVerifier::verify($planResult, $orderLines, $nodes, FulfillmentPlanner::MODE_COST);
        if (! $isValid) {
            throw new \RuntimeException('Fulfillment plan failed verification.');
        }

        $splits = [];
        foreach ($planResult['allocations'] as $nodeId => $skuAllocations) {
            foreach ($skuAllocations as $sku => $qty) {
                if ($qty <= 0) {
                    continue;
                }

                $split = RetFulfillmentSplit::create([
                    'id' => (string) Str::uuid(),
                    'split_code' => 'SPL-'.strtoupper(Str::random(8)),
                    'order_id' => $order->id,
                    'sku' => $sku,
                    'quantity' => $qty,
                    'allocated_quantity' => $qty,
                    'fulfillment_location_type' => 'DARK_STORE',
                    'facility_id' => $nodeId,
                    'source_location_id' => $nodeId,
                    'source_type' => 'DARK_STORE',
                    'status' => 'PLANNED',
                ]);

                $splits[] = $split;
            }
        }

        $this->audit(
            action: 'ret_fulfillment_planned',
            auditable: $order,
            newValues: ['total_cost' => $planResult['total_cost'], 'splits_count' => count($splits)],
            impactType: 'fulfillment_operations',
        );

        return [
            'order_id' => $order->id,
            'splits' => $splits,
            'certificate' => $planResult['certificate'] ?? [],
        ];
    }
}
