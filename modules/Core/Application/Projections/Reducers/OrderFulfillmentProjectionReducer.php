<?php

declare(strict_types=1);

namespace Modules\Core\Application\Projections\Reducers;

use Modules\Core\Application\Projections\ProjectionReducerInterface;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Reducer Proyeksi Order Fulfillment (Store)
 */
class OrderFulfillmentProjectionReducer implements ProjectionReducerInterface
{
    public function supportedTopics(): array
    {
        return [
            'store.order.*',
            'store.order.paid.v1',
        ];
    }

    public function reduce(array $currentState, SimEventSpine $event): array
    {
        $payload = (array) $event->payload;
        $orderId = (string) ($payload['order_id'] ?? 'unknown');
        $amount = (int) ($payload['amount_idr'] ?? $payload['amount'] ?? 0);

        $currentState['metrics']['total_amount'] = ($currentState['metrics']['total_amount'] ?? 0) + $amount;

        $orders = $currentState['domains']['store']['orders'] ?? [];
        $orders[$orderId] = [
            'status' => 'paid',
            'amount_idr' => $amount,
            'customer_id' => $payload['customer_id'] ?? null,
            'items_count' => count((array) ($payload['items'] ?? [])),
            'last_event_id' => $event->event_id,
        ];

        $currentState['domains']['store']['orders'] = $orders;
        $currentState['domains']['store']['paid_orders_count'] = count($orders);

        return $currentState;
    }
}
