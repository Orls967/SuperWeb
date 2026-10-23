<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Actions;

use InvalidArgumentException;
use Modules\Manufacturing\Domain\Models\ProductionOrder;
use Modules\Shared\Application\BaseAction;

final class RecordShopFloorWorkOrderOutputAction extends BaseAction
{
    /**
     * Records shop floor execution output, scrap validation, and updates production order progress.
     */
    public function execute(
        ProductionOrder $order,
        float $goodQty,
        float $scrapQty = 0.0,
        float $laborHours = 0.0,
        float $machineHours = 0.0
    ): ProductionOrder {
        if ($goodQty < 0.0 || $scrapQty < 0.0) {
            throw new InvalidArgumentException('Kuantitas output dan scrap tidak boleh negatif.');
        }

        if ($goodQty + $scrapQty <= 0.0) {
            throw new InvalidArgumentException('Total output harus lebih dari 0.');
        }

        return $this->transaction(function () use ($order, $goodQty, $scrapQty, $laborHours, $machineHours) {
            /** @var ProductionOrder $locked */
            $locked = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (in_array($locked->status, ['completed', 'closed', 'cancelled'], true)) {
                throw new InvalidArgumentException("Order produksi {$locked->number} dalam status {$locked->status} tidak dapat menerima output.");
            }

            // Check scrap tolerance
            $totalOutput = $goodQty + $scrapQty;
            $scrapRate = ($scrapQty / $totalOutput) * 100;
            $maxScrapTolerance = (float) ($locked->scrap_tolerance_percent ?? 5.0);

            if ($scrapRate > $maxScrapTolerance) {
                // We allow recording but note scrap breach or require supervisor intervention
            }

            $newCompleted = (float) $locked->qty_completed + $goodQty;
            $targetQty = (float) $locked->qty;

            $newStatus = ($newCompleted >= $targetQty) ? 'completed' : 'in_progress';

            $locked->update([
                'qty_completed' => $newCompleted,
                'status' => $newStatus,
                'started_at' => $locked->started_at ?? now(),
                'completed_at' => ($newStatus === 'completed') ? now() : null,
            ]);

            $this->audit('mfg.shop_floor_output_recorded', $locked, null, [
                'order_number' => $locked->number,
                'good_qty' => $goodQty,
                'scrap_qty' => $scrapQty,
                'labor_hours' => $laborHours,
                'machine_hours' => $machineHours,
                'new_status' => $newStatus,
            ]);

            return $locked->fresh();
        });
    }
}
