<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Resto\Domain\Enums\ConsumedState;
use Modules\Resto\Domain\Enums\ItemSource;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Exceptions\InvalidOrderOperationException;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\OrderItem;
use Modules\Resto\Domain\Models\TableSession;

class PresentHidangAction
{
    /**
     * @param  array<int, int>  $trayIdsWithQty  [tray_id => qty] or list of tray_ids (default qty 1)
     */
    public function handle(TableSession $session, array $trayIdsWithQty): Order
    {
        return DB::transaction(function () use ($session, $trayIdsWithQty) {
            $order = Order::where('table_session_id', $session->id)
                ->where('status', OrderStatus::OPEN)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                throw new InvalidOrderOperationException("Tidak ditemukan pesanan aktif untuk sesi meja #{$session->id}.");
            }

            $isAssoc = ! array_is_list($trayIdsWithQty);

            foreach ($trayIdsWithQty as $key => $val) {
                if ($isAssoc && is_numeric($key)) {
                    $trayId = (int) $key;
                    $qty = is_numeric($val) ? max(1, (int) $val) : 1;
                } else {
                    $trayId = (int) $val;
                    $qty = 1;
                }

                $tray = DisplayTray::where('id', $trayId)
                    ->where('outlet_id', $session->outlet_id)
                    ->lockForUpdate()
                    ->first();

                if ($tray === null || $tray->portions_remaining < $qty) {
                    continue; // Skip trays that are not found or lack portions
                }

                $menuItem = $tray->menuItem;
                $price = $menuItem?->base_price ?? 0;

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $tray->menu_item_id,
                    'tray_id' => $tray->id,
                    'name_snapshot' => $menuItem?->name ?: "Piring Etalase #{$tray->id}",
                    'unit_price_snapshot' => $price,
                    'qty' => $qty,
                    'line_total' => $price * $qty,
                    'source' => ItemSource::HIDANG,
                    'consumed_state' => ConsumedState::PRESENTED,
                    'cogs_snapshot' => $tray->cost_per_portion,
                ]);

                $tray->status = TrayStatus::IN_SERVICE;
                $tray->save();
            }

            return $order->load(['items.menuItem', 'items.tray']);
        });
    }
}
