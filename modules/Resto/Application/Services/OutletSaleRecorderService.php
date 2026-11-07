<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Services;

use Illuminate\Support\Str;
use Modules\Resto\Application\Actions\AddOrderItemAction;
use Modules\Resto\Application\Actions\PayOrderAction;
use Modules\Resto\Contracts\OutletSaleRecorder;
use Modules\Resto\Domain\Enums\OrderChannel;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Order;

final class OutletSaleRecorderService implements OutletSaleRecorder
{
    public function __construct(
        private readonly AddOrderItemAction $addItemAction,
        private readonly PayOrderAction $payAction,
    ) {}

    /**
     * @param  list<array{sku: string, qty: int}>  $lines
     * @return array{order_id: int, total_minor: int, cogs_minor: int}
     */
    public function record(int $outletId, array $lines, int $tabRef, string $idempotencyKey): array
    {
        $existing = Order::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return [
                'order_id' => $existing->id,
                'total_minor' => $existing->grand_total,
                'cogs_minor' => intdiv($existing->grand_total * 3000, 10000),
            ];
        }

        $orderNumber = 'ORD-BAR-'.$tabRef.'-'.substr(hash('sha256', $idempotencyKey), 0, 8);
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'outlet_id' => $outletId,
            'number' => $orderNumber,
            'channel' => OrderChannel::DINE_IN,
            'status' => OrderStatus::OPEN,
            'idempotency_key' => $idempotencyKey,
            'subtotal' => 0,
            'grand_total' => 0,
            'guest_name' => "VIP Tab #{$tabRef}",
        ]);

        $totalMinor = 0;
        $cogsMinor = 0;

        foreach ($lines as $line) {
            $sku = $line['sku'];
            $qty = max(1, $line['qty']);

            $menuItem = MenuItem::firstOrCreate(
                ['sku' => $sku],
                [
                    'outlet_id' => $outletId,
                    'name' => "Bottle / Item {$sku}",
                    'base_price' => 500_000,
                    'is_active' => true,
                ]
            );

            $orderItem = $this->addItemAction->handle(
                order: $order,
                menuItemId: $menuItem->id,
                qty: $qty,
                priceOverride: $menuItem->base_price
            );

            $totalMinor += $orderItem->line_total;
            $cogsMinor += intdiv($orderItem->line_total * 3000, 10000); // Estimasi COGS 30%
        }

        $order->update([
            'subtotal' => $totalMinor,
            'grand_total' => $totalMinor,
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
            'payment_method' => 'VIP_TAB',
        ]);

        return [
            'order_id' => $order->id,
            'total_minor' => $totalMinor,
            'cogs_minor' => $cogsMinor,
        ];
    }
}
