<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Application\Actions;

use Modules\CloudKitchen\Domain\Models\CateringOrder;
use Modules\Shared\Application\BaseAction;

class BatchCourierMultiDropDeliveryAction extends BaseAction
{
    /**
     * Group ready catering orders into a multi-drop courier delivery batch.
     *
     * @param  array<int>  $orderIds
     * @return array{batch_code: string, orders_count: int, courier_payout_idr: int, status: string}
     */
    public function handle(
        array $orderIds,
        string $courierName = 'AutoServe Express Fleet',
        int $baseDropFeeIdr = 15000,
        int $perStopFeeIdr = 5000
    ): array {
        return $this->transaction(function () use (
            $orderIds,
            $courierName,
            $baseDropFeeIdr,
            $perStopFeeIdr
        ) {
            $orders = CateringOrder::whereIn('id', $orderIds)
                ->whereIn('status', ['preparing', 'pending'])
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                throw new \DomainException('No eligible orders found for courier batching.');
            }

            $batchCode = 'BATCH-'.date('YmdHis').'-'.count($orders);
            $courierPayoutIdr = $baseDropFeeIdr + (count($orders) * $perStopFeeIdr);

            foreach ($orders as $order) {
                $order->status = 'in_transit';
                $order->save();
            }

            $idempotencyKey = hash('sha256', "ck:batch:{$batchCode}:".implode(',', $orderIds));

            $this->audit(
                action: 'cloud_kitchen.courier.batched',
                auditable: $orders->first(),
                oldValues: null,
                newValues: [
                    'batch_code' => $batchCode,
                    'courier_name' => $courierName,
                    'orders_count' => $orders->count(),
                    'payout_idr' => $courierPayoutIdr,
                ],
                correlationId: "ck_bat_{$batchCode}",
                impactType: 'logistics_dispatch'
            );

            $this->outbox(
                eventType: 'CloudKitchenCourierBatched',
                payload: [
                    'batch_code' => $batchCode,
                    'order_ids' => $orderIds,
                    'courier_name' => $courierName,
                    'courier_payout_idr' => $courierPayoutIdr,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'batch_code' => $batchCode,
                'orders_count' => $orders->count(),
                'courier_payout_idr' => $courierPayoutIdr,
                'status' => 'in_transit',
            ];
        });
    }
}
