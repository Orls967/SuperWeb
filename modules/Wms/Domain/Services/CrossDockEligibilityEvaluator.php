<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

/**
 * Menentukan apakah stok inbound dapat langsung dialihkan ke dock outbound (cross-docking) tanpa masuk rak.
 */
final class CrossDockEligibilityEvaluator
{
    /**
     * @param  list<array{outbound_order_id: string, product_id: int, needed_qty: int, dock_ready: bool}>  $openBackorders
     * @return array{
     *     cross_dock_qty: int,
     *     putaway_remainder_qty: int,
     *     matched_orders: list<array{outbound_order_id: string, allocated_qty: int}>
     * }
     */
    public function evaluate(int $productId, int $incomingQty, array $openBackorders): array
    {
        $remaining = max(0, $incomingQty);
        $crossDockQty = 0;
        $matched = [];

        foreach ($openBackorders as $order) {
            if ($remaining <= 0) {
                break;
            }
            if ((int) $order['product_id'] !== $productId || ! $order['dock_ready']) {
                continue;
            }
            $need = max(0, (int) $order['needed_qty']);
            if ($need === 0) {
                continue;
            }
            $take = min($remaining, $need);
            $matched[] = [
                'outbound_order_id' => (string) $order['outbound_order_id'],
                'allocated_qty' => $take,
            ];
            $crossDockQty += $take;
            $remaining -= $take;
        }

        return [
            'cross_dock_qty' => $crossDockQty,
            'putaway_remainder_qty' => $remaining,
            'matched_orders' => $matched,
        ];
    }
}
