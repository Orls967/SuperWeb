<?php

declare(strict_types=1);

namespace Modules\Store\Application\Actions;

use App\Models\User;
use Exception;
use Modules\Shared\Application\BaseAction;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;

/**
 * Pembeli membuka sengketa: dana tetap tertahan di escrow sampai admin memutuskan,
 * dan pencairan otomatis 3 hari dibatalkan.
 */
class DisputeC2cOrderAction extends BaseAction
{
    public function execute(Order $order, User $actor, string $reason): Order
    {
        return $this->transaction(function () use ($order, $actor, $reason) {
            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->isC2c()) {
                throw new Exception('Pesanan ini bukan transaksi C2C.');
            }

            if ((int) $order->user_id !== (int) $actor->id && ! $actor->isAdmin()) {
                throw new Exception('Hanya pembeli yang dapat mengajukan sengketa.');
            }

            if (! in_array($order->status, [OrderStatus::AWAITING_HANDOVER, OrderStatus::AWAITING_CONFIRMATION], true)) {
                throw new Exception("Sengketa hanya dapat diajukan selama dana masih di escrow. Status saat ini: {$order->status->label()}");
            }

            if (trim($reason) === '') {
                throw new Exception('Alasan sengketa wajib diisi.');
            }

            $order->update([
                'status' => OrderStatus::DISPUTED,
                'disputed_at' => now(),
                'dispute_reason' => $reason,
                'auto_capture_at' => null,
            ]);

            return $order->fresh();
        });
    }
}
