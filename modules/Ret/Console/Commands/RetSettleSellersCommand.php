<?php

declare(strict_types=1);

namespace Modules\Ret\Console\Commands;

use Illuminate\Console\Command;
use Modules\Ret\Application\Actions\SettleRetailSellerEscrowAction;
use Modules\Ret\Domain\Models\RetOrder;

class RetSettleSellersCommand extends Command
{
    protected $signature = 'ret:settle-sellers';

    protected $description = 'Penyelesaian escrow terjadwal untuk seluruh pesanan ritel yang telah terkirim (DELIVERED)';

    public function handle(SettleRetailSellerEscrowAction $action): int
    {
        $this->info('Starting retail seller escrow settlement batch...');

        $orders = RetOrder::where('status', 'DELIVERED')
            ->whereNotNull('seller_id')
            ->orderBy('id')
            ->get();

        $settledCount = 0;
        foreach ($orders as $order) {
            try {
                $idempotencyKey = "ret:settle:cron:{$order->id}";
                $action->execute([
                    'order_id' => $order->id,
                    'idempotency_key' => $idempotencyKey,
                ]);
                $settledCount++;
            } catch (\Throwable $e) {
                $this->warn("Failed to settle order [{$order->order_number}]: {$e->getMessage()}");
            }
        }

        $this->info("Completed seller settlement batch: {$settledCount} orders settled.");

        return self::SUCCESS;
    }
}
