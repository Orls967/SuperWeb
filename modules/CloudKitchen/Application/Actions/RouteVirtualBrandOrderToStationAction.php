<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Application\Actions;

use Modules\CloudKitchen\Domain\Models\CateringOrder;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;
use Modules\Shared\Application\BaseAction;

class RouteVirtualBrandOrderToStationAction extends BaseAction
{
    /**
     * Route virtual brand meal order to the nearest kitchen station with capacity headroom.
     *
     * @return array{order: CateringOrder, kitchen_id: int, kitchen_code: string, status: string}
     */
    public function handle(
        int $subscriptionId,
        string $mealSlot,
        int $mealCostIdr = 45000,
        ?string $deliveryDate = null
    ): array {
        return $this->transaction(function () use (
            $subscriptionId,
            $mealSlot,
            $mealCostIdr,
            $deliveryDate
        ) {
            $date = $deliveryDate ?? now()->toDateString();

            // Find active kitchen with available capacity headroom
            $kitchen = CloudKitchen::where('is_active', true)
                ->whereRaw('current_hourly_load < hourly_capacity')
                ->orderBy('current_hourly_load', 'asc')
                ->first();

            if (! $kitchen) {
                // Fallback to primary active kitchen
                $kitchen = CloudKitchen::where('is_active', true)->firstOrFail();
            }

            $orderCode = 'CK-ORD-'.date('Ymd').'-'.rand(1000, 9999);
            $idempotencyKey = hash('sha256', "ck:route:{$subscriptionId}:{$date}:{$mealSlot}");

            $order = CateringOrder::firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'order_code' => $orderCode,
                    'subscription_id' => $subscriptionId,
                    'kitchen_id' => $kitchen->id,
                    'delivery_date' => $date,
                    'meal_slot' => $mealSlot,
                    'status' => 'preparing',
                    'meal_cost_idr' => $mealCostIdr,
                ]
            );

            // Increment load
            $kitchen->increment('current_hourly_load');

            $this->audit(
                action: 'cloud_kitchen.order.routed',
                auditable: $order,
                oldValues: null,
                newValues: [
                    'order_code' => $order->order_code,
                    'kitchen_id' => $kitchen->id,
                    'meal_slot' => $mealSlot,
                    'meal_cost_idr' => $mealCostIdr,
                ],
                correlationId: "ck_route_{$order->order_code}",
                impactType: 'kitchen_routing'
            );

            $this->outbox(
                eventType: 'CloudKitchenOrderRouted',
                payload: [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'kitchen_id' => $kitchen->id,
                    'kitchen_code' => $kitchen->kitchen_code,
                    'meal_slot' => $mealSlot,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'order' => $order,
                'kitchen_id' => $kitchen->id,
                'kitchen_code' => $kitchen->kitchen_code,
                'status' => 'preparing',
            ];
        });
    }
}
