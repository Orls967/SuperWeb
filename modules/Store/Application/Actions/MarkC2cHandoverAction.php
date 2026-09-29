<?php

declare(strict_types=1);

namespace Modules\Store\Application\Actions;

use App\Models\User;
use Exception;
use Modules\Shared\Application\BaseAction;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;

/**
 * Penjual menyatakan kendaraan telah diserahkan ke pembeli.
 * Mulai saat ini pembeli punya 3 hari untuk konfirmasi sebelum dana otomatis cair.
 */
class MarkC2cHandoverAction extends BaseAction
{
    public function execute(Order $order, User $actor): Order
    {
        if (! $order->isC2c()) {
            throw new Exception('Pesanan ini bukan transaksi C2C.');
        }

        if ((int) $order->seller_id !== (int) $actor->id && ! $actor->isAdmin()) {
            throw new Exception('Hanya penjual yang dapat menandai serah terima kendaraan.');
        }

        if ($order->status !== OrderStatus::AWAITING_HANDOVER) {
            throw new Exception("Serah terima hanya dapat dilakukan pada pesanan yang menunggu penyerahan. Status saat ini: {$order->status->label()}");
        }

        $order->update([
            'status' => OrderStatus::AWAITING_CONFIRMATION,
            'handover_at' => now(),
            'auto_capture_at' => now()->addDays(Order::C2C_AUTO_CAPTURE_DAYS),
        ]);

        return $order->fresh();
    }
}
