<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Logistics\Contracts\ShipmentBooking;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Manufacturing\Domain\Models\Bom;
use Modules\Manufacturing\Domain\Models\DowntimeLog;
use Modules\Manufacturing\Domain\Models\FgReceipt;
use Modules\Manufacturing\Domain\Models\MaterialBalance;
use Modules\Manufacturing\Domain\Models\MaterialIssue;
use Modules\Manufacturing\Domain\Models\MaterialIssueLot;
use Modules\Manufacturing\Domain\Models\MaterialLot;
use Modules\Manufacturing\Domain\Models\OperationReport;
use Modules\Manufacturing\Domain\Models\PlannedOrder;
use Modules\Manufacturing\Domain\Models\ProductionOrder;
use Modules\Manufacturing\Domain\Models\ReworkRecord;
use Modules\Manufacturing\Domain\Models\SubcontractReceipt;
use Modules\Manufacturing\Domain\Models\WipTransfer;
use Modules\Procurement\Contracts\MrpRequisitionProposer;

/**
 * Eksekusi produksi / shop floor (Fase 37): order, issue bahan FIFO/FEFO,
 * pelaporan operasi, downtime, penerimaan FG, WIP, scrap/rework, subkontrak.
 *
 * Stok material pabrik disimpan di mfg_material_balances + mfg_material_lots;
 * stok tidak boleh negatif — kekurangan menjadi alert, bukan kegagalan diam.
 */
class ProductionService
{
    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly ApprovalEngineInterface $approvals,
        private readonly MrpRequisitionProposer $prProposer,
        private readonly ShipmentBooking $shipmentBooking,
    ) {}

    // ── 37.1 Order produksi ──────────────────────────────────────────────

    /**
     * @param  array{material_id: string, qty: float, plant_id?: string, planned_order_id?: string,
     *   kind?: string, release_date?: string, due_date?: string, scrap_tolerance_percent?: float,
     *   notes?: string, routing_id?: string}  $data
     */
    public function createProductionOrder(array $data, User $creator): ProductionOrder
    {
        $qty = (float) ($data['qty'] ?? 0);
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty order produksi harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($data, $creator, $qty) {
            $number = $this->numbering->nextNumber('MFG', 'MPO', false, 'MPO/{ENT}/');

            $order = ProductionOrder::create([
                'number' => $number,
                'material_id' => $data['material_id'],
                'plant_id' => $data['plant_id'] ?? null,
                'planned_order_id' => $data['planned_order_id'] ?? null,
                'routing_id' => $data['routing_id'] ?? null,
                'kind' => $data['kind'] ?? 'standard',
                'qty' => $qty,
                'qty_completed' => 0,
                'status' => 'planned',
                'release_date' => $data['release_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'scrap_tolerance_percent' => $data['scrap_tolerance_percent'] ?? 2,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $creator->id,
            ]);

            if ($data['planned_order_id'] ?? null) {
                $planned = PlannedOrder::find($data['planned_order_id']);
                if ($planned !== null && $planned->status === 'firm') {
                    $planned->update(['status' => 'converted', 'production_order_id' => $order->id]);
                }
            }

            return $order;
        });
    }

    /** Transisi status dengan guard state machine. */
    public function transition(ProductionOrder $order, string $to, User $actor): ProductionOrder
    {
        return DB::transaction(function () use ($order, $to, $actor) {
            /** @var ProductionOrder $locked */
            $locked = ProductionOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($locked->status === $to) {
                return $locked; // idempoten
            }

            if (! $locked->canTransitionTo($to)) {
                throw new InvalidArgumentException(
                    "Transisi {$locked->status} → {$to} tidak sah untuk order {$locked->number}."
                );
            }

            $now = now();
            $updates = ['status' => $to];
            match ($to) {
                'released' => $updates['released_at'] = $now,
                'in_progress' => $updates['started_at'] = $now,
                'completed' => $updates['completed_at'] = $now,
                'closed' => $updates['closed_at'] = $now,
                default => null,
            };

            if ($to === 'completed') {
                $this->backflush($locked, $actor);
            }

            if ($to === 'cancelled' && $locked->planned_order_id !== null) {
                PlannedOrder::where('id', $locked->planned_order_id)
                    ->where('status', 'converted')
                    ->update(['status' => 'cancelled']);
            }

            $locked->update($updates);

            return $locked->fresh();
        });
    }

    // ── 37.2 Issue bahan & backflush ─────────────────────────────────────

    /**
     * Keluarkan bahan dari lot terpakai FIFO (produced_at) atau FEFO
     * (expiry). Kekurangan dicatat pada kolom alert — stok tidak pernah
     * negatif.
     *
     * @param  array<int, array{material_id: string, qty: float, method?: string, lot_id?: string}>  $lines
     * @return array<int, MaterialIssue>
     */
    public function issueMaterials(ProductionOrder $order, array $lines, User $actor, string $kind = 'issue'): array
    {
        if (! in_array($kind, ['issue', 'backflush', 'return'], true)) {
            throw new InvalidArgumentException('Jenis issue tidak dikenal.');
        }

        return DB::transaction(function () use ($order, $lines, $actor, $kind) {
            $locked = $this->lockOrder($order->getKey());
            if (! in_array($locked->status, ['released', 'in_progress'], true)) {
                throw new InvalidArgumentException("Issue bahan hanya untuk order released/in_progress (status {$locked->status}).");
            }

            $created = [];
            foreach ($lines as $line) {
                $qty = (float) ($line['qty'] ?? 0);
                if ($qty <= 0) {
                    throw new InvalidArgumentException('Qty issue harus lebih besar dari nol.');
                }

                $materialId = (string) $line['material_id'];
                $method = $line['method'] ?? 'fifo';

                if (! empty($line['lot_id'])) {
                    $lot = MaterialLot::find($line['lot_id']);
                    if ($lot === null || (string) $lot->material_id !== $materialId) {
                        throw new InvalidArgumentException('Lot tidak cocok dengan material issue.');
                    }
                    $allocations = $this->consumeFromLot($lot->id, $qty);
                    $unlotted = 0.0;
                } else {
                    $stock = $this->consumeFromStock($materialId, $qty, $method);
                    $allocations = $stock['allocations'];
                    $unlotted = $stock['unlotted'];
                }

                $consumed = array_sum(array_column($allocations, 'qty')) + $unlotted;
                $alert = null;
                if ($consumed + 0.000001 < $qty) {
                    $alert = $allocations === [] && $unlotted === 0.0 ? 'no_lot' : 'shortage';
                }

                $issue = MaterialIssue::create([
                    'production_order_id' => $locked->id,
                    'material_id' => $materialId,
                    'lot_id' => $line['lot_id'] ?? null,
                    'qty' => $consumed,
                    'kind' => $kind,
                    'method' => $method,
                    'alert' => $alert,
                    'created_by_user_id' => $actor->id,
                ]);

                foreach ($allocations as $allocation) {
                    MaterialIssueLot::create([
                        'material_issue_id' => $issue->id,
                        'lot_id' => $allocation['lot_id'],
                        'qty' => $allocation['qty'],
                    ]);
                }

                $created[] = $issue;
            }

            return $created;
        });
    }

    /**
     * Konsumsi satu lot tertentu (dikunci).
     *
     * @return array<int, array{lot_id: string, qty: float}>
     */
    private function consumeFromLot(string $lotId, float $qty): array
    {
        /** @var MaterialLot $lot */
        $lot = MaterialLot::query()->lockForUpdate()->findOrFail($lotId);

        if ($lot->status !== 'active') {
            return [];
        }

        $take = min(max(0.0, (float) $lot->qty), $qty);
        if ($take <= 0) {
            return [];
        }

        $lot->qty = bcsub((string) $lot->qty, (string) $take, 6);
        $lot->status = bccomp((string) $lot->qty, '0', 6) <= 0 ? 'consumed' : 'active';
        $lot->save();

        $this->decrementBalance((string) $lot->material_id, $take);

        return [['lot_id' => (string) $lot->id, 'qty' => $take]];
    }

    /**
     * Konsumsi lintas lot: FIFO (produced_at) atau FEFO (expiry terdekat).
     * Sisa kebutuhan dapat ditarik dari saldo tanpa lot — tidak
     * dialokasikan ke `mfg_material_issue_lots` karena tidak punya lot.
     *
     * @return array{allocations: array<int, array{lot_id: string, qty: float}>, unlotted: float}
     */
    private function consumeFromStock(string $materialId, float $qty, string $method): array
    {
        $lots = MaterialLot::where('material_id', $materialId)
            ->where('status', 'active')
            ->where('qty', '>', 0)
            ->get();

        $lots = match ($method) {
            'fefo' => $lots->sortBy(fn (MaterialLot $l) => ($l->expiry_date?->toDateString() ?? '9999-12-31'))->values(),
            default => $lots->sortBy(fn (MaterialLot $l) => ($l->produced_at?->toDateString() ?? $l->created_at->toDateString()))->values(),
        };

        $remaining = $qty;
        $allocations = [];
        $unlotted = 0.0;

        foreach ($lots as $lot) {
            if ($remaining <= 0.000001) {
                break;
            }

            $take = min(max(0.0, (float) $lot->qty), $remaining);
            if ($take <= 0) {
                continue;
            }

            $lot->qty = bcsub((string) $lot->qty, (string) $take, 6);
            $lot->status = bccomp((string) $lot->qty, '0', 6) <= 0 ? 'consumed' : 'active';
            $lot->save();

            $this->decrementBalance((string) $lot->material_id, $take);

            $allocations[] = ['lot_id' => (string) $lot->id, 'qty' => $take];
            $remaining -= $take;
        }

        // Sisa dari saldo tanpa lot (bila saldo > 0 tapi semua lot habis).
        if ($remaining > 0.000001) {
            $balance = MaterialBalance::where('material_id', $materialId)->lockForUpdate()->first();
            if ($balance !== null && (float) $balance->qty_on_hand > 0) {
                $take = min((float) $balance->qty_on_hand, $remaining);
                $this->decrementBalance($materialId, $take);
                $unlotted += $take;
            }
        }

        return ['allocations' => $allocations, 'unlotted' => $unlotted];
    }

    private function decrementBalance(string $materialId, float $qty): void
    {
        $balance = MaterialBalance::query()->lockForUpdate()->firstWhere('material_id', $materialId);
        if ($balance === null) {
            return;
        }

        $newQty = bcsub((string) $balance->qty_on_hand, (string) $qty, 6);
        if (bccomp($newQty, '0', 6) < 0) {
            throw new InvalidArgumentException('Stok material tidak boleh negatif (guard hard).');
        }

        $balance->qty_on_hand = $newQty;
        $balance->save();
    }

    /**
     * Backflush: kebutuhan BOM − yang sudah di-issue → keluarkan sisanya
     * (bila cukup). Kekurangan menjadi alert 'backflush_shortage'.
     */
    public function backflush(ProductionOrder $order, User $actor): array
    {
        $bom = $this->effectiveBom((string) $order->material_id, $order->due_date?->toDateString());
        if ($bom === null) {
            return [];
        }

        $target = (float) $order->qty;
        $lines = [];
        foreach ($bom->lines as $bomLine) {
            if ($bomLine->is_by_product || $bomLine->is_co_product) {
                continue;
            }

            $required = $bomLine->requiredQty($target);
            $already = (float) MaterialIssue::where('production_order_id', $order->id)
                ->where('material_id', $bomLine->input_material_id)
                ->whereIn('kind', ['issue', 'backflush'])
                ->sum('qty');
            $missing = $required - $already;

            if ($missing > 0.000001) {
                $lines[] = ['material_id' => (string) $bomLine->input_material_id, 'qty' => $missing, 'method' => 'fifo'];
            }
        }

        if ($lines === []) {
            return [];
        }

        return $this->issueMaterials($order, $lines, $actor, 'backflush');
    }

    private function effectiveBom(string $materialId, ?string $date): ?Bom
    {
        return Bom::where('output_material_id', $materialId)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->get()
            ->first(fn (Bom $b) => $b->isEffective($date ?? now()->toDateString()));
    }

    // ── 37.3 Pelaporan operasi ───────────────────────────────────────────

    /** Mulai operasi (belum selesai). */
    public function startOperation(
        ProductionOrder $order,
        int $sequence,
        ?string $workCenterId = null,
        ?int $workerId = null,
        ?int $routingOperationId = null,
    ): OperationReport {
        return DB::transaction(function () use ($order, $sequence, $workCenterId, $workerId, $routingOperationId) {
            $locked = $this->lockOrder($order->getKey());
            if (! in_array($locked->status, ['released', 'in_progress'], true)) {
                throw new InvalidArgumentException("Operasi hanya untuk order aktif (status {$locked->status}).");
            }

            $report = OperationReport::create([
                'production_order_id' => $locked->id,
                'routing_operation_id' => $routingOperationId,
                'work_center_id' => $workCenterId,
                'worker_id' => $workerId,
                'sequence' => $sequence,
                'started_at' => now(),
                'duration_minutes' => 0,
            ]);

            if ($locked->status === 'released') {
                $locked->update(['status' => 'in_progress', 'started_at' => $locked->started_at ?? now()]);
            }

            return $report;
        });
    }

    /**
     * Selesaikan operasi dengan qty baik/scrap/rework dan durasi aktual.
     *
     * @return array{report: OperationReport, order: ProductionOrder}
     */
    public function finishOperation(
        OperationReport $report,
        float $qtyGood,
        float $qtyScrap = 0,
        float $qtyRework = 0,
        ?string $notes = null,
    ): array {
        return DB::transaction(function () use ($report, $qtyGood, $qtyScrap, $qtyRework, $notes) {
            /** @var OperationReport $locked */
            $locked = OperationReport::query()->lockForUpdate()->findOrFail($report->getKey());

            if ($locked->finished_at !== null) {
                return ['report' => $locked, 'order' => $locked->productionOrder];
            }

            $locked->finished_at = now();
            $locked->duration_minutes = max(1, (int) ceil(now()->diffInMinutes($locked->started_at)));
            $locked->qty_good = $qtyGood;
            $locked->qty_scrap = $qtyScrap;
            $locked->qty_rework = $qtyRework;
            $locked->notes = $notes;
            $locked->save();

            $order = $locked->productionOrder;
            $order->qty_completed = bcadd((string) $order->qty_completed, (string) $qtyGood, 6);
            $order->save();

            return ['report' => $locked->fresh(), 'order' => $order->fresh()];
        });
    }

    // ── 37.4 Downtime ────────────────────────────────────────────────────

    public function startDowntime(string $workCenterId, string $reasonCode, User $actor, ?int $productionOrderId = null, ?string $detail = null): DowntimeLog
    {
        if (! in_array($reasonCode, DowntimeLog::REASON_CODES, true)) {
            throw new InvalidArgumentException('Kode downtime tidak dikenal: '.$reasonCode);
        }

        return DowntimeLog::create([
            'work_center_id' => $workCenterId,
            'production_order_id' => $productionOrderId,
            'reason_code' => $reasonCode,
            'detail' => $detail,
            'started_at' => now(),
            'created_by_user_id' => $actor->id,
        ]);
    }

    public function endDowntime(DowntimeLog $log): DowntimeLog
    {
        return DB::transaction(function () use ($log) {
            /** @var DowntimeLog $locked */
            $locked = DowntimeLog::query()->lockForUpdate()->findOrFail($log->getKey());

            if ($locked->ended_at !== null) {
                return $locked; // idempoten
            }

            $locked->ended_at = now();
            $locked->minutes = max(1, (int) ceil(now()->diffInMinutes($locked->started_at)));
            $locked->save();

            return $locked;
        });
    }

    // ── 37.5 Penerimaan FG ───────────────────────────────────────────────

    /**
     * Terima hasil produksi ke stok (lot baru), termasuk by-product.
     * Invarian diperiksa: Σ FG receipt ≤ qty_completed ± toleransi.
     *
     * @param  array{serials?: array<int, string>}  $options
     */
    public function receiveFg(
        ProductionOrder $order,
        float $qty,
        User $receiver,
        ?string $lotNumber = null,
        array $options = [],
        bool $byProduct = false,
    ): FgReceipt {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty penerimaan FG harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($order, $qty, $receiver, $lotNumber, $options, $byProduct) {
            $locked = $this->lockOrder($order->getKey());
            if (! in_array($locked->status, ['in_progress', 'completed'], true)) {
                throw new InvalidArgumentException("Penerimaan FG hanya untuk order in_progress/completed (status {$locked->status}).");
            }

            $materialId = $byProduct
                ? (string) ($options['by_product_material_id'] ?? $locked->material_id)
                : (string) $locked->material_id;

            // Invarian 37.9: FG utama tidak boleh melebihi hasil lapangan
            // (qty_completed) meski berstatus in_progress/completed.
            if (! $byProduct) {
                $already = (float) FgReceipt::where('production_order_id', $locked->id)
                    ->where('material_id', $locked->material_id)
                    ->where('by_product', false)
                    ->sum('qty');
                $allowed = (float) $locked->qty_completed;
                if ($already + $qty > $allowed + 0.000001) {
                    throw new InvalidArgumentException(sprintf(
                        'Penerimaan FG melebihi hasil lapangan: sudah %.6f + %.6f > qty_completed %.6f.',
                        $already,
                        $qty,
                        $allowed
                    ));
                }
            }

            $receipt = FgReceipt::create([
                'production_order_id' => $locked->id,
                'material_id' => $materialId,
                'qty' => $qty,
                'serials' => $options['serials'] ?? null,
                'by_product' => $byProduct,
                'notes' => $options['notes'] ?? null,
                'received_by_user_id' => $receiver->id,
            ]);

            // Lot FG masuk stok.
            MaterialLot::create([
                'material_id' => $materialId,
                'lot_number' => $lotNumber ?: 'FG-'.strtoupper(substr($locked->number, -6)).'-'.now()->format('ymdHis'),
                'qty' => $qty,
                'produced_at' => now()->toDateString(),
                'expiry_date' => $options['expiry_date'] ?? null,
                'source_type' => 'production',
                'source_ref' => $locked->number,
                'status' => 'active',
            ]);

            $balance = MaterialBalance::firstOrCreate(['material_id' => $materialId], ['qty_on_hand' => 0, 'qty_reserved' => 0]);
            $balance->qty_on_hand = bcadd((string) $balance->qty_on_hand, (string) $qty, 6);
            $balance->save();

            return $receipt;
        });
    }

    // ── 37.6 WIP ─────────────────────────────────────────────────────────

    /** Transfer WIP antar operasi (in_transit → receive). */
    public function transferWip(ProductionOrder $order, ?int $fromOp, ?int $toOp, float $qty, User $actor): WipTransfer
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty WIP harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($order, $fromOp, $toOp, $qty, $actor) {
            $locked = $this->lockOrder($order->getKey());
            if (! in_array($locked->status, ['released', 'in_progress'], true)) {
                throw new InvalidArgumentException('Transfer WIP hanya untuk order aktif.');
            }

            return WipTransfer::create([
                'production_order_id' => $locked->id,
                'from_operation_id' => $fromOp,
                'to_operation_id' => $toOp,
                'qty' => $qty,
                'status' => 'in_transit',
                'created_by_user_id' => $actor->id,
            ]);
        });
    }

    public function receiveWip(WipTransfer $transfer): WipTransfer
    {
        return DB::transaction(function () use ($transfer) {
            /** @var WipTransfer $locked */
            $locked = WipTransfer::query()->lockForUpdate()->findOrFail($transfer->getKey());

            if ($locked->status === 'received') {
                return $locked; // idempoten
            }

            $locked->status = 'received';
            $locked->save();

            return $locked;
        });
    }

    /** Laporan WIP: qty in-transit dan belum received per order. */
    public function wipReport(?string $productionOrderId = null): array
    {
        $query = WipTransfer::query()->where('status', 'in_transit');
        if ($productionOrderId !== null) {
            $query->where('production_order_id', $productionOrderId);
        }

        return $query->get()
            ->groupBy('production_order_id')
            ->map(fn ($transfers) => ['qty' => (float) $transfers->sum('qty'), 'count' => $transfers->count()])
            ->all();
    }

    // ── 37.7 Scrap & rework ──────────────────────────────────────────────

    /**
     * Catat scrap/rework. Bila total scrap melebihi toleransi order
     * (scrap_tolerance_percent), flag ncr_required → hook QMS Fase 39.
     *
     * @return array{record: ReworkRecord, scrapPct: float, ncrRequired: bool}
     */
    public function recordScrapRework(
        ProductionOrder $order,
        string $kind,
        float $qty,
        string $reason,
        User $actor,
        int $costIdr = 0,
    ): array {
        if (! in_array($kind, ['scrap', 'rework'], true)) {
            throw new InvalidArgumentException('Jenis harus scrap atau rework.');
        }

        return DB::transaction(function () use ($order, $kind, $qty, $reason, $actor, $costIdr) {
            $locked = $this->lockOrder($order->getKey());
            if ($qty <= 0) {
                throw new InvalidArgumentException('Qty scrap/rework harus lebih besar dari nol.');
            }

            $totalScrap = (float) ReworkRecord::where('production_order_id', $locked->id)
                ->where('kind', 'scrap')->sum('qty');
            $totalGood = max(1.0, (float) $locked->qty_completed);
            $scrapPct = (($totalScrap + $qty) / max($totalGood, 1.0)) * 100;

            $ncr = $kind === 'scrap' && $scrapPct > (float) $locked->scrap_tolerance_percent;

            $record = ReworkRecord::create([
                'production_order_id' => $locked->id,
                'material_id' => $locked->material_id,
                'reason' => $reason,
                'qty' => $qty,
                'kind' => $kind,
                'cost_idr' => $costIdr,
                'ncr_required' => $ncr,
                'created_by_user_id' => $actor->id,
            ]);

            // Scrap mengurangi qty FG yang dapat diterima.
            if ($kind === 'scrap') {
                $locked->qty_completed = bcsub((string) $locked->qty_completed, (string) $qty, 6);
                $locked->save();
            }

            return ['record' => $record, 'scrapPct' => $scrapPct, 'ncrRequired' => $ncr];
        });
    }

    // ── 37.8 Subkontrak ──────────────────────────────────────────────────

    /**
     * Kirim bahan ke subkon (issue dari stok) — qty terkirim tercatat.
     *
     * @return array{receipt: SubcontractReceipt, issues: array<int, MaterialIssue>}
     */
    public function shipToSubcontract(
        ProductionOrder $order,
        array $materialLines,
        float $qtyIn,
        User $actor,
        ?string $supplierId = null,
        ?string $shipmentRef = null,
        array $route = [],
    ): array {
        if ($order->kind !== 'subcontract') {
            throw new InvalidArgumentException('Subkontrak hanya untuk order ber-kind subcontract.');
        }

        return DB::transaction(function () use ($order, $materialLines, $qtyIn, $actor, $supplierId, $shipmentRef, $route) {
            $issues = $this->issueMaterials($order, $materialLines, $actor);

            // 37.8 Kirim bahan ke subkon via Logistics (ShipmentBooking),
            // idempoten per (source_type, source_id).
            if ($route['origin_code'] ?? null) {
                $existing = Shipment::query()
                    ->where('source_type', 'manufacturing_subcontract')
                    ->where('source_id', (string) $order->id)
                    ->first();

                if ($existing !== null) {
                    $shipmentRef = $existing->tracking_number;
                } else {
                    $packages = array_map(fn (array $line) => [
                        'weight_g' => max(100, (int) ceil((float) ($line['qty'] ?? 1)) * 1000),
                        'length_mm' => 300, 'width_mm' => 200, 'height_mm' => 200,
                        'description' => 'Bahan subkontrak order '.$order->number,
                    ], $materialLines);

                    if ($packages !== []) {
                        $booked = $this->shipmentBooking->bookForOrder($actor, [
                            'origin_code' => (string) $route['origin_code'],
                            'destination_address' => [
                                'street' => (string) ($route['street'] ?? 'Subkontrak'),
                                'city' => (string) ($route['city'] ?? 'Banjarmasin'),
                            ],
                            'consignee_name' => (string) ($route['consignee_name'] ?? 'Subkontrak'),
                            'consignee_phone' => (string) ($route['consignee_phone'] ?? '-'),
                            'packages' => $packages,
                            'declared_value_idr' => max(1, (int) ($route['declared_value_idr'] ?? 1)),
                            'source_type' => 'manufacturing_subcontract',
                            'source_id' => (string) $order->id,
                            // Ledger menolak 1 entri; ongkir simpanan non-nol (biaya riil di landed cost 34.7).
                            'amount_idr' => 1,
                        ]);
                        $shipmentRef = $booked['tracking_number'];
                    }
                }
            }

            $receipt = SubcontractReceipt::create([
                'production_order_id' => $order->id,
                'supplier_id' => $supplierId,
                'shipment_ref' => $shipmentRef,
                'qty_in' => $qtyIn,
                'qty_out' => 0,
                'status' => 'shipping',
                'created_by_user_id' => $actor->id,
            ]);

            return ['receipt' => $receipt, 'issues' => $issues];
        });
    }

    /**
     * Terima hasil olahan subkontrak + biaya jasa; PR jasa dibuat
     * via contract Procurement (36.6).
     *
     * @return array{receipt: SubcontractReceipt, prRef: string|null}
     */
    public function receiveFromSubcontract(SubcontractReceipt $receipt, float $qtyOut, int $serviceCostIdr, User $actor): array
    {
        return DB::transaction(function () use ($receipt, $qtyOut, $serviceCostIdr, $actor) {
            /** @var SubcontractReceipt $locked */
            $locked = SubcontractReceipt::query()->lockForUpdate()->findOrFail($receipt->getKey());

            if (in_array($locked->status, ['received', 'settled'], true)) {
                return ['receipt' => $locked, 'prRef' => $locked->pr_ref]; // replay aman
            }

            if ($locked->status !== 'shipping') {
                throw new InvalidArgumentException("Status subkontrak {$locked->status} tidak dapat diterima.");
            }
            if ($qtyOut <= 0) {
                throw new InvalidArgumentException('Qty hasil olahan harus lebih besar dari nol.');
            }

            $order = ProductionOrder::query()->lockForUpdate()->findOrFail($locked->production_order_id);

            // Hasil olahan subkontrak adalah hasil lapangan order itu.
            $order->qty_completed = bcadd((string) $order->qty_completed, (string) $qtyOut, 6);
            $order->save();

            // Hasil olahan masuk stok sebagai penerimaan FG.
            $this->receiveFg($order, $qtyOut, $actor, null, ['notes' => 'Hasil olahan subkontrak '.$locked->id]);

            $locked->qty_out = $qtyOut;
            $locked->service_cost_idr = $serviceCostIdr;
            $locked->status = 'received';

            // PR jasa subkontrak (bila ada biaya).
            $prRef = null;
            if ($serviceCostIdr > 0 && $locked->pr_ref === null) {
                $prRef = $this->prProposer->proposeRequisition(
                    [[
                        'material_code' => 'JASA-SUBKON',
                        'qty' => 1,
                        'unit' => 'lot',
                        'lead_time_days' => 0,
                        'moq' => 1,
                        'estimated_unit_price_idr' => $serviceCostIdr,
                    ]],
                    $actor,
                    'SUBKON-'.substr($locked->id, 0, 8),
                );
                $locked->pr_ref = $prRef;
            }

            $locked->save();

            return ['receipt' => $locked->fresh(), 'prRef' => $prRef];
        });
    }

    // ── 37.9 Invarian ────────────────────────────────────────────────────

    /**
     * Invarian order: Σ issued + scrap ≤ kebutuhan BOM ± toleransi,
     * hasil = BOM × qty ± toleransi. True = konsisten.
     *
     * @return array{ok: bool, issues: array<int, string>}
     */
    public function checkInvariants(ProductionOrder $order): array
    {
        $issues = [];

        $bom = $this->effectiveBom((string) $order->material_id, $order->due_date?->toDateString());
        if ($bom !== null) {
            foreach ($bom->lines as $bomLine) {
                if ($bomLine->is_by_product || $bomLine->is_co_product) {
                    continue;
                }

                $required = $bomLine->requiredQty((float) $order->qty);
                $issued = (float) MaterialIssue::where('production_order_id', $order->id)
                    ->where('material_id', $bomLine->input_material_id)
                    ->whereIn('kind', ['issue', 'backflush'])
                    ->sum('qty');

                $tolerance = max($required * 0.05, 0.000001);
                if ($issued > $required + $tolerance) {
                    $issues[] = sprintf(
                        'Bahan %s terlalu banyak: issued %.6f > required %.6f + 5%%',
                        $bomLine->inputMaterial?->code ?? $bomLine->input_material_id,
                        $issued,
                        $required
                    );
                }
            }
        }

        // Hasil + scrap tidak boleh melampaui target + toleransi besar.
        $scrap = (float) ReworkRecord::where('production_order_id', $order->id)
            ->where('kind', 'scrap')->sum('qty');
        $good = (float) $order->qty_completed;
        $target = (float) $order->qty;
        $tolerancePct = max(2.0, (float) $order->scrap_tolerance_percent);

        if ($good + $scrap > $target * (1 + $tolerancePct / 100) + 0.000001) {
            $issues[] = sprintf(
                'Hasil+scrap %.6f melampaui target %.6f (toleransi %.2f%%)',
                $good + $scrap,
                $target,
                $tolerancePct
            );
        }

        if ($good + $scrap < $target * (1 - $tolerancePct / 100) - 0.000001
            && in_array($order->status, ['completed', 'closed'], true)) {
            $issues[] = sprintf(
                'Order %s status %s tapi hasil %.6f < target %.6f − toleransi %.2f%%',
                $order->number,
                $order->status,
                $good,
                $target,
                $tolerancePct
            );
        }

        return ['ok' => $issues === [], 'issues' => $issues];
    }

    private function lockOrder(string $id): ProductionOrder
    {
        return ProductionOrder::query()->lockForUpdate()->findOrFail($id);
    }
}
