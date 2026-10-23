<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Application\Actions;

use Modules\CloudKitchen\Domain\Models\CateringOrder;
use Modules\Shared\Application\BaseAction;

class SettleVirtualBrandRevenueSplitAction extends BaseAction
{
    /**
     * Settle delivered catering meal order revenue split across kitchen, virtual brand IP, and platform.
     *
     * @return array{order: CateringOrder, kitchen_fee_idr: int, brand_royalty_idr: int, platform_fee_idr: int, total_idr: int}
     */
    public function handle(int $orderId): array
    {
        return $this->transaction(function () use ($orderId) {
            $order = CateringOrder::where('id', $orderId)->lockForUpdate()->firstOrFail();

            $totalIdr = $order->meal_cost_idr;

            // 60% Kitchen Operator, 25% Virtual Brand Creator, 15% Platform
            $kitchenFeeIdr = (int) round($totalIdr * 0.60);
            $brandRoyaltyIdr = (int) round($totalIdr * 0.25);
            $platformFeeIdr = max(0, $totalIdr - $kitchenFeeIdr - $brandRoyaltyIdr);

            $order->status = 'delivered';
            $order->save();

            $idempotencyKey = hash('sha256', "ck:revenue:settle:{$orderId}:{$totalIdr}");

            $this->audit(
                action: 'cloud_kitchen.revenue.settled',
                auditable: $order,
                oldValues: ['status' => 'in_transit'],
                newValues: [
                    'status' => 'delivered',
                    'kitchen_fee_idr' => $kitchenFeeIdr,
                    'brand_royalty_idr' => $brandRoyaltyIdr,
                    'platform_fee_idr' => $platformFeeIdr,
                ],
                correlationId: "ck_rev_{$order->order_code}",
                impactType: 'revenue_split'
            );

            $this->outbox(
                eventType: 'CloudKitchenRevenueSettled',
                payload: [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'kitchen_id' => $order->kitchen_id,
                    'total_idr' => $totalIdr,
                    'kitchen_fee_idr' => $kitchenFeeIdr,
                    'brand_royalty_idr' => $brandRoyaltyIdr,
                    'platform_fee_idr' => $platformFeeIdr,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'order' => $order,
                'kitchen_fee_idr' => $kitchenFeeIdr,
                'brand_royalty_idr' => $brandRoyaltyIdr,
                'platform_fee_idr' => $platformFeeIdr,
                'total_idr' => $totalIdr,
            ];
        });
    }
}
