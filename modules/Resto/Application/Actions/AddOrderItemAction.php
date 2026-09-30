<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Resto\Domain\Enums\ConsumedState;
use Modules\Resto\Domain\Enums\ItemSource;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Exceptions\InvalidOrderOperationException;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\OrderItem;

class AddOrderItemAction
{
    public function handle(Order $order, int $menuItemId, int $qty = 1, ?int $priceOverride = null): OrderItem
    {
        return DB::transaction(function () use ($order, $menuItemId, $qty, $priceOverride) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedOrder->status, [OrderStatus::OPEN, OrderStatus::AWAITING_PAYMENT], true)) {
                throw new InvalidOrderOperationException("Pesanan #{$lockedOrder->id} tidak dapat ditambahkan item.");
            }

            $menuItem = MenuItem::findOrFail($menuItemId);
            $unitPrice = $priceOverride ?? $menuItem->base_price;
            $lineTotal = $unitPrice * $qty;

            return OrderItem::create([
                'order_id' => $lockedOrder->id,
                'menu_item_id' => $menuItem->id,
                'tray_id' => null,
                'name_snapshot' => $menuItem->name,
                'unit_price_snapshot' => $unitPrice,
                'qty' => $qty,
                'line_total' => $lineTotal,
                'source' => ItemSource::PESAN,
                'consumed_state' => ConsumedState::CONSUMED,
                'cogs_snapshot' => 0,
            ]);
        });
    }
}
