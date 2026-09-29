<?php

declare(strict_types=1);

namespace Modules\Store\Application\Actions;

use Exception;
use Modules\Shared\Application\BaseAction;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;

class UpdateOrderStatusAction extends BaseAction
{
    public function ship(Order $order, string $trackingNumber): Order
    {
        if (! in_array($order->status, [OrderStatus::PAID, OrderStatus::PROCESSING], true)) {
            throw new Exception("Hanya pesanan berstatus Dibayar/Diproses yang dapat dikirim. Status saat ini: {$order->status->label()}");
        }

        $order->update([
            'status' => OrderStatus::SHIPPED,
            'tracking_number' => $trackingNumber,
        ]);

        return $order->fresh();
    }

    public function complete(Order $order): Order
    {
        if ($order->status === OrderStatus::COMPLETED) {
            return $order;
        }

        if (in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
            throw new Exception('Pesanan yang telah dibatalkan tidak dapat diselesaikan.');
        }

        $order->markAsCompleted();

        return $order->fresh();
    }
}
