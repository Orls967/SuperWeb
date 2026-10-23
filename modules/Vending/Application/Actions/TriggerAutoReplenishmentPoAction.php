<?php

declare(strict_types=1);

namespace Modules\Vending\Application\Actions;

use Modules\Shared\Application\BaseAction;
use Modules\Vending\Domain\Models\VendingAutoPo;
use Modules\Vending\Domain\Models\VendingRestockTask;
use Modules\Vending\Domain\Models\VendingUnit;

class TriggerAutoReplenishmentPoAction extends BaseAction
{
    /**
     * Trigger automated Purchase Order & restock task when vending machine stock reaches critical threshold.
     *
     * @return array{auto_po: VendingAutoPo, restock_task: VendingRestockTask, qty_ordered: int, estimated_total_idr: int}
     */
    public function handle(
        int $vendingUnitId,
        string $itemCode,
        int $costPerUnitIdr = 15000,
        int $dailyPlafonIdr = 5000000,
        int $supplierId = 1
    ): array {
        return $this->transaction(function () use (
            $vendingUnitId,
            $itemCode,
            $costPerUnitIdr,
            $dailyPlafonIdr,
            $supplierId
        ) {
            $unit = VendingUnit::where('id', $vendingUnitId)->lockForUpdate()->firstOrFail();

            $qtyToOrder = max(10, $unit->max_capacity - $unit->current_stock);
            $estimatedTotalIdr = $qtyToOrder * $costPerUnitIdr;

            if ($estimatedTotalIdr > $dailyPlafonIdr) {
                throw new \DomainException("Auto-PO estimated total IDR {$estimatedTotalIdr} exceeds daily plafon limit IDR {$dailyPlafonIdr}.");
            }

            $autoPoCode = 'APO-VEN-'.date('Ymd').'-'.substr(hash('sha256', "{$unit->unit_code}:{$itemCode}:{$qtyToOrder}"), 0, 6);

            $autoPo = VendingAutoPo::create([
                'auto_po_code' => $autoPoCode,
                'outlet_code' => $unit->unit_code,
                'item_code' => $itemCode,
                'qty_ordered' => $qtyToOrder,
                'estimated_total_idr' => $estimatedTotalIdr,
                'daily_plafon_idr' => $dailyPlafonIdr,
                'status' => 'approved',
                'supplier_id' => $supplierId,
            ]);

            $taskCode = 'RST-'.date('Ymd').'-'.rand(100, 999);
            $restockTask = VendingRestockTask::create([
                'task_code' => $taskCode,
                'vending_unit_id' => $unit->id,
                'suggested_qty' => $qtyToOrder,
                'status' => 'pending',
            ]);

            $idempotencyKey = hash('sha256', "vending:autopo:{$autoPoCode}");

            $this->audit(
                action: 'vending.autopo.triggered',
                auditable: $autoPo,
                oldValues: null,
                newValues: [
                    'auto_po_code' => $autoPoCode,
                    'unit_code' => $unit->unit_code,
                    'qty_ordered' => $qtyToOrder,
                    'estimated_total_idr' => $estimatedTotalIdr,
                ],
                correlationId: "ven_apo_{$autoPoCode}",
                impactType: 'replenishment_order'
            );

            $this->outbox(
                eventType: 'VendingAutoReplenishmentTriggered',
                payload: [
                    'auto_po_code' => $autoPoCode,
                    'unit_id' => $unit->id,
                    'unit_code' => $unit->unit_code,
                    'qty_ordered' => $qtyToOrder,
                    'estimated_total_idr' => $estimatedTotalIdr,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'auto_po' => $autoPo,
                'restock_task' => $restockTask,
                'qty_ordered' => $qtyToOrder,
                'estimated_total_idr' => $estimatedTotalIdr,
            ];
        });
    }
}
