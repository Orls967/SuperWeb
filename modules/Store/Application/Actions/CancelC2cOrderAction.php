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

/**
 * Batalkan transaksi C2C: dana escrow dikembalikan penuh ke pembeli,
 * unit dilepas kembali dan listing penjual diaktifkan lagi.
 */
class CancelC2cOrderAction extends BaseAction
{
    public function __construct(
        private readonly PaymentGateway $paymentGateway,
        private readonly InventoryService $inventoryService,
    ) {}

    public function execute(Order $order, string $reason = 'Transaksi C2C dibatalkan'): Order
    {
        if (! $order->isC2c()) {
            throw new Exception('Pesanan ini bukan transaksi C2C.');
        }

        if (! $order->status->isEscrowHeld()) {
            throw new Exception("Pesanan dengan status {$order->status->label()} tidak dapat dibatalkan.");
        }

        $intent = $order->paymentIntents()
            ->where('status', PaymentIntentStatus::HELD->value)
            ->latest()
            ->first();

        if ($intent !== null) {
            $this->paymentGateway->release($intent, 'c2c_release_'.$order->uuid);
        }

        foreach ($order->items as $item) {
            if ($item->reservation_id) {
                try {
                    $this->inventoryService->release($item->reservation_id, "Pembatalan C2C: {$reason}");
                } catch (\Throwable) {
                    // reservasi mungkin sudah dilepas sebelumnya
                }
            }

            // Aktifkan kembali listing agar kendaraan bisa dijual ke pembeli lain
            $item->product?->update(['is_listed' => true]);
        }

        $order->update([
            'status' => OrderStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        return $order->fresh(['items.product', 'paymentIntents']);
    }
}
