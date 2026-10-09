<?php

declare(strict_types=1);

namespace Modules\Store\Console\Commands;

use Illuminate\Console\Command;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;

class CancelStaleOrdersCommand extends Command
{
    protected $signature = 'store:cancel-stale-orders {--minutes=30 : Batas waktu menit pesanan pending}';

    protected $description = 'Batalkan pesanan pending payment yang melewati batas waktu dan lepaskan reservasi stok';

    public function handle(InventoryService $inventoryService): int
    {
        $minutes = (int) $this->option('minutes');
        $cutoff = now()->subMinutes($minutes);

        $count = 0;
        $query = Order::where('status', OrderStatus::PENDING_PAYMENT->value)
            ->where('created_at', '<=', $cutoff)
            ->with('items');

        $query->chunkById(200, function ($staleOrders) use ($inventoryService, $minutes, &$count): void {
            foreach ($staleOrders as $order) {
                foreach ($order->items as $item) {
                    if ($item->reservation_id) {
                        $inventoryService->release(
                            $item->reservation_id,
                            "Auto-cancel: Pesanan {$order->number} tidak dibayar dalam {$minutes} menit"
                        );
                    }
                }

                $order->status = OrderStatus::CANCELLED;
                $order->cancelled_at = now();
                $order->cancellation_reason = "Kedaluwarsa: Tidak dibayar dalam {$minutes} menit";
                $order->save();

                $count++;
                $this->line("Pesanan {$order->number} berhasil dibatalkan dan reservasi stok dilepaskan.");
            }
        });

        if ($count === 0) {
            $this->info("Tidak ada pesanan pending payment yang kedaluwarsa (> {$minutes} menit).");

            return self::SUCCESS;
        }

        $this->info("Total {$count} pesanan kedaluwarsa berhasil dibatalkan.");

        return self::SUCCESS;
    }
}
