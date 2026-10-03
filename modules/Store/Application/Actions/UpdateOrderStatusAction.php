<?php

declare(strict_types=1);

namespace Modules\Store\Application\Actions;

use Exception;
use Illuminate\Support\Str;
use Modules\Core\Contracts\AcquiresVehicle;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Shared\Application\BaseAction;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;

class UpdateOrderStatusAction extends BaseAction
{
    public function __construct(
        private readonly AcquiresVehicle $acquirer,
    ) {}

    public function ship(Order $order, string $trackingNumber): Order
    {
        return $this->transaction(function () use ($order, $trackingNumber) {
            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($order->status, [OrderStatus::PAID, OrderStatus::PROCESSING], true)) {
                throw new Exception("Hanya pesanan berstatus Dibayar/Diproses yang dapat dikirim. Status saat ini: {$order->status->label()}");
            }

            $oldStatus = $order->status->value;
            $order->update([
                'status' => OrderStatus::SHIPPED,
                'tracking_number' => $trackingNumber,
            ]);

            $this->audit(
                action: 'store.order.shipped',
                auditable: $order,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => OrderStatus::SHIPPED->value, 'tracking_number' => $trackingNumber],
                context: ['order_number' => $order->order_number],
                correlationId: 'order_ship_'.$order->id,
                impactType: 'state'
            );

            return $order->fresh();
        });
    }

    public function complete(Order $order): Order
    {
        return $this->transaction(function () use ($order) {
            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
                throw new Exception('Pesanan yang telah dibatalkan tidak dapat diselesaikan.');
            }

            $oldStatus = $order->status->value;
            if ($order->status !== OrderStatus::COMPLETED) {
                $order->status = OrderStatus::COMPLETED;
                $order->save();
            }

            $this->fulfillMissingCarPurchases($order);

            $this->audit(
                action: 'store.order.completed',
                auditable: $order,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => OrderStatus::COMPLETED->value],
                context: ['order_number' => $order->order_number, 'total_price' => $order->total_price],
                correlationId: 'order_complete_'.$order->id,
                impactType: 'state'
            );

            return $order->fresh();
        });
    }

    /**
     * Acquire car units still missing from the buyer's garage for this order.
     */
    private function fulfillMissingCarPurchases(Order $order): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;

            if (! $product || ! $product->is_car || $product->productable_type !== 'dex_car' || ! $product->productable_id) {
                continue;
            }

            $acquiredCount = Vehicle::withTrashed()
                ->where('user_id', $order->user_id)
                ->where('car_id', $product->productable_id)
                ->where('acquired_via_type', 'order')
                ->where('acquired_via_id', $order->id)
                ->count();

            for ($index = $acquiredCount; $index < (int) $item->qty; $index++) {
                $this->acquirer->handle(
                    user: (int) $order->user_id,
                    car: (int) $product->productable_id,
                    plateNumber: 'B '.rand(1000, 9999).' '.strtoupper(Str::random(3)),
                    color: 'Hitam',
                    vin: 'VIN'.strtoupper(Str::random(14)),
                    odometerKm: 0,
                    acquiredViaType: 'order',
                    acquiredViaId: $order->id,
                );
            }
        }
    }
}
