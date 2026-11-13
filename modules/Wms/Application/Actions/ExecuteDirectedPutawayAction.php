<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\Algorithms\PutawaySlotter;
use Modules\Wms\Domain\Events\WarehouseTaskConfirmedEvent;
use Modules\Wms\Domain\Models\Bin;
use Modules\Wms\Domain\Models\BinStock;
use Modules\Wms\Domain\Models\Task;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\StateMachines\WarehouseTaskStateMachine;
use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * Eksekusi directed putaway berbasis PutawaySlotter (H1), SM 7-state, event, dan ledger D41-2.
 */
final class ExecuteDirectedPutawayAction
{
    public function __construct(
        private readonly PutawaySlotter $slotter,
        private readonly WarehouseTaskStateMachine $stateMachine,
        private readonly WmsLedgerPoster $poster,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @return array{
     *     mode: string,
     *     allocations: list<array{bin_id: string, qty: int}>,
     *     task_ids: list<int>,
     *     ledger_posted_idr: int
     * }
     */
    public function execute(
        int $warehouseId,
        int $productId,
        int $qtyBase,
        string $abcClass,
        int $unitCostIdr,
        string $referenceId,
        ?int $actorUserId = null,
        ?string $lotNumber = null,
        ?string $expiryDate = null,
    ): array {
        $warehouse = Warehouse::findOrFail($warehouseId);
        $binRows = DB::table('wms_bins as b')
            ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
            ->join('wms_zones as z', 'z.id', '=', 'r.zone_id')
            ->where('z.warehouse_id', $warehouseId)
            ->where('b.is_active', true)
            ->select('b.id', 'b.code', 'b.capacity')
            ->get();

        $binsInput = [];
        foreach ($binRows as $index => $bin) {
            $used = (int) DB::table('wms_bin_stocks')
                ->where('bin_id', $bin->id)
                ->sum('qty_base');
            $cap = (int) ($bin->capacity ?: 1000);
            $free = max(0, $cap - $used);
            if ($free > 0) {
                $binsInput[] = [
                    'id' => (string) $bin->id,
                    'distance' => ($index + 1) * 10,
                    'freeCapacity' => $free,
                ];
            }
        }

        $plan = $this->slotter->plan($qtyBase, $abcClass, $binsInput);

        return DB::transaction(function () use (
            $warehouse,
            $productId,
            $qtyBase,
            $unitCostIdr,
            $referenceId,
            $actorUserId,
            $lotNumber,
            $expiryDate,
            $plan
        ): array {
            $taskIds = [];
            $now = $this->clock->now();

            foreach ($plan['allocations'] as $alloc) {
                $binId = (int) $alloc['bin_id'];
                $allocQty = (int) $alloc['qty'];

                $stock = BinStock::firstOrNew([
                    'bin_id' => $binId,
                    'product_id' => $productId,
                    'lot_number' => $lotNumber,
                ]);
                $newBase = (int) ($stock->qty_base ?? 0) + $allocQty;
                $stock->qty_base = $newBase;
                $stock->qty = $newBase;
                $stock->expiry_date = $expiryDate ?? $stock->expiry_date;
                $stock->stock_status = $stock->stock_status ?: 'available';
                $stock->save();

                $bin = Bin::findOrFail($binId);
                $task = Task::create([
                    'warehouse_id' => $warehouse->id,
                    'kind' => 'putaway',
                    'status' => WarehouseTaskStateMachine::STATUS_CREATED,
                    'product_id' => $productId,
                    'to_bin_id' => $bin->id,
                    'qty' => $allocQty,
                    'assigned_to' => $actorUserId,
                ]);

                $this->stateMachine->transition($task, 'assign', $actorUserId);
                $this->stateMachine->transition($task, 'start', $actorUserId);
                $this->stateMachine->transition($task, 'confirm', $actorUserId, 'Directed putaway selesai');
                $this->stateMachine->transition($task, 'close', $actorUserId);

                Event::dispatch(new WarehouseTaskConfirmedEvent(
                    taskId: (int) $task->id,
                    warehouseId: (int) $warehouse->id,
                    kind: 'putaway',
                    actorUserId: $actorUserId,
                    occurredAt: $now->toIso8601String(),
                ));

                $taskIds[] = (int) $task->id;
            }

            $valueIdr = QuantityNormalizer::computeValueIdr($qtyBase, $unitCostIdr);
            $this->poster->postInboundReceipt((string) $warehouse->code, $valueIdr, $referenceId);

            return [
                'mode' => $plan['mode'],
                'allocations' => $plan['allocations'],
                'task_ids' => $taskIds,
                'ledger_posted_idr' => $valueIdr,
            ];
        });
    }
}
