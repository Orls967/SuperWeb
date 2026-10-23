<?php

declare(strict_types=1);

namespace Modules\Distribution\Application\Actions;

use InvalidArgumentException;
use Modules\Distribution\Domain\Models\DistOrder;
use Modules\Distribution\Domain\Models\DistOrderLine;
use Modules\Distribution\Domain\Models\Distributor;

class ProcessDistributorSellInOrderAction
{
    /**
     * Process sell-in order for distributor, enforcing credit limit guardrails and ATP allocation.
     *
     * @param  array<int, array{product_id: int, sku: string, name: string, qty: float|int, unit_price_idr: int}>  $items
     */
    public function execute(
        Distributor $distributor,
        array $items,
        string $orderNumber,
        int $discountIdr = 0,
        ?int $userId = null
    ): DistOrder {
        if (empty($items)) {
            throw new InvalidArgumentException('Order distributor harus memiliki minimal satu item produk.');
        }

        $subtotalIdr = 0;
        foreach ($items as $item) {
            $qty = (float) $item['qty'];
            $price = (int) $item['unit_price_idr'];
            $subtotalIdr += (int) round($qty * $price);
        }

        $taxableAmount = max(0, $subtotalIdr - $discountIdr);
        $ppnIdr = (int) round($taxableAmount * 0.11); // PPN 11%
        $totalIdr = $taxableAmount + $ppnIdr;

        // Credit limit guardrail
        $newExposure = (int) $distributor->credit_exposure_idr + $totalIdr;
        if ($distributor->credit_limit_idr > 0 && $newExposure > $distributor->credit_limit_idr) {
            throw new InvalidArgumentException(
                sprintf('Credit limit distributor terlampaui. Limit: Rp %d, Permintaan: Rp %d', $distributor->credit_limit_idr, $newExposure)
            );
        }

        $order = DistOrder::create([
            'number' => $orderNumber,
            'distributor_id' => (string) $distributor->id,
            'status' => 'allocated',
            'subtotal_idr' => $subtotalIdr,
            'discount_idr' => $discountIdr,
            'ppn_idr' => $ppnIdr,
            'total_idr' => $totalIdr,
            'currency' => 'IDR',
            'allocation_strategy' => 'priority',
            'created_by_user_id' => $userId,
            'allocated_at' => now(),
        ]);

        foreach ($items as $item) {
            $qty = (float) $item['qty'];
            $price = (int) $item['unit_price_idr'];
            $lineTotal = (int) round($qty * $price);

            DistOrderLine::create([
                'order_id' => (string) $order->id,
                'product_id' => (int) $item['product_id'],
                'sku' => $item['sku'],
                'name_snapshot' => $item['name'],
                'qty' => $qty,
                'allocated_qty' => $qty,
                'shipped_qty' => 0,
                'unit_price_idr' => $price,
                'line_total_idr' => $lineTotal,
                'line_status' => 'allocated',
            ]);
        }

        $distributor->update([
            'credit_exposure_idr' => $newExposure,
        ]);

        return $order->fresh(['lines']);
    }
}
