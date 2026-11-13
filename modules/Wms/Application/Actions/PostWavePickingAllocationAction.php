<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Application\Services\StockReservationService;
use Modules\Wms\Domain\Algorithms\PickPathPlanner;
use Modules\Wms\Domain\Events\WarehouseTaskConfirmedEvent;
use Modules\Wms\Domain\Models\BinStock;
use Modules\Wms\Domain\Models\Task;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\StateMachines\WarehouseTaskStateMachine;
use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * Alokasi wave picking outbound berbasis PickPathPlanner (H2), StockReservationService (X41-2), & ledger D41-3.
 */
final class PostWavePickingAllocationAction
{
    public function __construct(
        private readonly PickPathPlanner $planner,
        private readonly StockReservationService $reservationService,
        private readonly WarehouseTaskStateMachine $stateMachine,
        private readonly WmsLedgerPoster $poster,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @param  list<array{product_id: int, qty_base: int, unit_cost_idr: int}>  $lines
     * @return array{
     *     strategy: string,
     *     total_distance: int,
     *     sequence: list<array{aisle: int, pos: int, bin_id: string}>,
     *     task_ids: list<int>,
     *     ledger_posted_idr: int
     * }
     */
    public function execute(
        int $warehouseId,
        string $waveReference,
        array $lines,
        ?int $actorUserId = null,
        int $aisleWidth = 10,
        int $aisleLength = 100,
    ): array {
        $warehouse = Warehouse::findOrFail($warehouseId);

        return DB::transaction(function () use (
            $warehouse,
            $waveReference,
            $lines,
            $actorUserId,
            $aisleWidth,
            $aisleLength
        ): array {
            $picks = [];
            $taskIds = [];
            $totalValueIdr = 0;
            $now = $this->clock->now();

            foreach ($lines as $idx => $line) {
                $productId = (int) $line['product_id'];
                $qtyBase = (int) $line['qty_base'];
                $unitCostIdr = (int) $line['unit_cost_idr'];

                $stock = BinStock::query()
                    ->join('wms_bins as b', 'b.id', '=', 'wms_bin_stocks.bin_id')
                    ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
                    ->join('wms_zones as z', 'z.id', '=', 'r.zone_id')
                    ->where('z.warehouse_id', $warehouse->id)
                    ->where('wms_bin_stocks.product_id', $productId)
                    ->where('wms_bin_stocks.stock_status', 'available')
                    ->where('wms_bin_stocks.qty_base', '>=', $qtyBase)
                    ->orderByRaw('CASE WHEN wms_bin_stocks.expiry_date IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('wms_bin_stocks.expiry_date')
                    ->select('wms_bin_stocks.*', 'r.id as rack_id')
                    ->first();

                if ($stock === null) {
                    throw new DomainException("Stok FEFO tersedia tidak mencukupi untuk produk #{$productId}.");
                }

                $reservation = $this->reservationService->reserve(
                    warehouseId: (int) $warehouse->id,
                    productId: $productId,
                    qtyBase: $qtyBase,
                    referenceType: 'WMS_WAVE',
                    referenceId: $waveReference,
                    ttlMinutes: 60,
                    binId: (int) $stock->bin_id,
                    lotNumber: $stock->lot_number,
                    actorUserId: $actorUserId,
                );

                $this->reservationService->commit((int) $reservation->id);

                $newBase = (int) $stock->qty_base - $qtyBase;
                $stock->qty_base = $newBase;
                $stock->qty = $newBase;
                $stock->save();

                $task = Task::create([
                    'warehouse_id' => $warehouse->id,
                    'kind' => 'pick',
                    'status' => WarehouseTaskStateMachine::STATUS_CREATED,
                    'product_id' => $productId,
                    'from_bin_id' => $stock->bin_id,
                    'qty' => $qtyBase,
                    'assigned_to' => $actorUserId,
                ]);

                $this->stateMachine->transition($task, 'assign', $actorUserId);
                $this->stateMachine->transition($task, 'start', $actorUserId);
                $this->stateMachine->transition($task, 'confirm', $actorUserId, "Wave pick {$waveReference}");
                $this->stateMachine->transition($task, 'close', $actorUserId);

                Event::dispatch(new WarehouseTaskConfirmedEvent(
                    taskId: (int) $task->id,
                    warehouseId: (int) $warehouse->id,
                    kind: 'pick',
                    actorUserId: $actorUserId,
                    occurredAt: $now->toIso8601String(),
                ));

                $picks[] = [
                    'aisle' => max(1, ((int) $stock->rack_id % 10) + 1),
                    'pos' => min($aisleLength, (($idx + 1) * 15) % $aisleLength + 1),
                    'bin_id' => (string) $stock->bin_id,
                ];
                $taskIds[] = (int) $task->id;
                $totalValueIdr += QuantityNormalizer::computeValueIdr($qtyBase, $unitCostIdr);
            }

            $plan = $this->planner->plan($aisleWidth, $aisleLength, $picks);
            $this->poster->postOutboundIssue((string) $warehouse->code, $totalValueIdr, $waveReference);

            return [
                'strategy' => $plan['strategy'],
                'total_distance' => $plan['total_distance'],
                'sequence' => $plan['sequence'],
                'task_ids' => $taskIds,
                'ledger_posted_idr' => $totalValueIdr,
            ];
        });
    }
}
