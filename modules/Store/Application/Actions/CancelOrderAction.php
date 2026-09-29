<?php

declare(strict_types=1);

namespace Modules\Store\Application\Actions;

use Exception;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Domain\Enums\PaymentIntentStatus;
use Modules\Shared\Application\BaseAction;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;

class CancelOrderAction extends BaseAction
{
    public function __construct(
        private readonly PaymentGateway $paymentGateway,
        private readonly InventoryService $inventoryService
    ) {}

    public function execute(Order $order, string $reason = 'Pembatalan pesanan'): Order
    {
        if (in_array($order->status, [OrderStatus::SHIPPED, OrderStatus::COMPLETED], true)) {
            throw new Exception('Pesanan yang sudah dikirim atau selesai tidak dapat dibatalkan.');
        }

        if (in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
            throw new Exception('Pesanan ini sudah dibatalkan sebelumnya.');
        }

        // If order was paid/processing, execute full refund via PaymentGateway
        if (in_array($order->status, [OrderStatus::PAID, OrderStatus::PROCESSING], true)) {
            $intent = $order->paymentIntents()
                ->where('status', PaymentIntentStatus::CAPTURED)
                ->latest()
                ->first();

            if ($intent) {
                $this->paymentGateway->refund($intent, $order->payableAmount(), $reason);

                return $order->fresh();
            }
        }

        // If pending payment, release any active reservations
        if ($order->status === OrderStatus::PENDING_PAYMENT) {
            foreach ($order->items as $item) {
                if ($item->reservation_id) {
                    try {
                        $this->inventoryService->release($item->reservation_id, "Pembatalan order: {$reason}");
                    } catch (\Throwable) {
                        // ignore if already released
                    }
                }
            }

            $order->update([
                'status' => OrderStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
        }

        return $order->fresh();
    }
}
