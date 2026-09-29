<?php

declare(strict_types=1);

namespace Modules\Store\Console\Commands;

use Illuminate\Console\Command;
use Modules\Store\Application\Actions\ConfirmC2cReceiptAction;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;

class AutoCaptureC2cOrdersCommand extends Command
{
    protected $signature = 'store:auto-capture-c2c';

    protected $description = 'Cairkan dana escrow C2C ke penjual bila pembeli tidak konfirmasi dalam 3 hari';

    public function handle(ConfirmC2cReceiptAction $confirmReceipt): int
    {
        $orders = Order::where('status', OrderStatus::AWAITING_CONFIRMATION->value)
            ->whereNotNull('auto_capture_at')
            ->where('auto_capture_at', '<=', now())
            ->get();

        $this->info("Menemukan {$orders->count()} pesanan C2C yang melewati batas konfirmasi.");

        foreach ($orders as $order) {
            try {
                $confirmReceipt->execute($order);
                $this->info("✓ Order {$order->number} dicairkan otomatis ke penjual.");
            } catch (\Throwable $e) {
                $this->error("✗ Gagal mencairkan order {$order->number}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
