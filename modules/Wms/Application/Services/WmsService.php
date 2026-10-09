<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Logistics\Contracts\ShipmentBooking;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Wms\Domain\Models\Bin;
use Modules\Wms\Domain\Models\BinStock;
use Modules\Wms\Domain\Models\CycleCount;
use Modules\Wms\Domain\Models\CycleCountLine;
use Modules\Wms\Domain\Models\DockAppointment;
use Modules\Wms\Domain\Models\PackingList;
use Modules\Wms\Domain\Models\Rack;
use Modules\Wms\Domain\Models\Replenishment;
use Modules\Wms\Domain\Models\Slotting;
use Modules\Wms\Domain\Models\Task;
use Modules\Wms\Domain\Models\Transfer;
use Modules\Wms\Domain\Models\TransferLine;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\Models\Wave;
use Modules\Wms\Domain\Models\Zone;

/**
 * Gudang & pusat distribusi (Fase 41): hirarki, stok bin, tugas
 * putaway/pick/pack/stage, transfer in-transit + cross-dock, cycle count
 * (approval), replenishment/slotting ABC, dock & packing list, audit.
 *
 * Stok bin adalah subledger di atas `InventoryService` (stok produk global).
 * Mutasi besar (GRN/penjualan) tetap memakai InventoryService; pergerakan
 * antar-bin adalah pergeseran posisi yang tidak mengubah total stok.
 */
class WmsService
{
    public const TRANSIT_WAREHOUSE = 'TRN';

    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly ApprovalEngineInterface $approvals,
        private readonly InventoryService $inventory,
        private readonly ShipmentBooking $shipment,
    ) {}

    // ── 41.1 Hirarki gudang → zona → rak → bin ─────────────────────────

    public function createWarehouse(array $data): Warehouse
    {
        $code = strtoupper($data['code']);
        if (Warehouse::where('code', $code)->exists()) {
            throw new InvalidArgumentException("Kode gudang {$code} sudah dipakai.");
        }

        return Warehouse::create([
            'code' => $code, 'name' => $data['name'],
            'kind' => $data['kind'] ?? 'dc', 'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null, 'is_active' => true,
        ]);
    }

    public function createZone(Warehouse $warehouse, string $code, string $name, string $kind = 'storage'): Zone
    {
        return Zone::firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => strtoupper($code)],
            ['name' => $name, 'kind' => $kind, 'is_active' => true]
        );
    }

    public function createRack(Zone $zone, string $code, ?string $name = null): Rack
    {
        return Rack::firstOrCreate(
            ['zone_id' => $zone->id, 'code' => strtoupper($code)],
            ['name' => $name]
        );
    }

    public function createBin(Rack $rack, string $code, int $capacityUnits = 0, bool $isPickFace = false): Bin
    {
        return Bin::firstOrCreate(
            ['rack_id' => $rack->id, 'code' => strtoupper($code)],
            ['capacity_units' => $capacityUnits, 'is_pick_face' => $isPickFace, 'is_active' => true]
        );
    }

    // ── 41.2 Stok per bin ───────────────────────────────────────────────

    /**
     * Simpan (putaway) qty ke bin. Kapasitas bin menolak kelebihan.
     * Total stok produk TIDAK berubah — ini pergeseran posisi.
     *
     * @return array{stock: BinStock, task: ?Task}
     */
    public function putaway(Bin $bin, int $productId, float $qty, User $operator, ?string $lotNumber = null, string $status = 'available'): array
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty putaway harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($bin, $productId, $qty, $operator, $lotNumber, $status) {
            $lockedBin = Bin::query()->lockForUpdate()->findOrFail($bin->getKey());
            if ($lockedBin->capacityReached((int) ceil($qty))) {
                throw new InvalidArgumentException('Kapasitas bin terlampaui.');
            }

            $stock = BinStock::updateOrCreate(
                [
                    'bin_id' => $lockedBin->id, 'product_id' => $productId,
                    'lot_number' => $lotNumber, 'serial_number' => null, 'status' => $status,
                ],
                []
            );
            $stock->qty = bcadd((string) $stock->qty, (string) $qty, 6);
            $stock->save();

            $task = Task::create([
                'kind' => 'putaway', 'status' => 'done', 'priority' => 'normal',
                'bin_id' => $lockedBin->id, 'product_id' => $productId,
                'lot_number' => $lotNumber, 'qty' => $qty,
                'completed_at' => now(), 'created_by_user_id' => $operator->id,
            ]);

            return ['stock' => $stock, 'task' => $task];
        });
    }

    /** Ambil (pick) qty dari bin: FIFO per lot bila lot tidak dispesifikkan. */
    public function pick(Bin $bin, int $productId, float $qty, User $operator, ?string $lotNumber = null, string $method = 'fifo'): Task
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty pick harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($bin, $productId, $qty, $operator, $lotNumber, $method) {
            $stocks = BinStock::where('bin_id', $bin->id)
                ->where('product_id', $productId)
                ->where('status', 'available')
                ->when($lotNumber !== null, fn ($q) => $q->where('lot_number', $lotNumber))
                ->where('qty', '>', 0)
                ->get();

            $stocks = match ($method) {
                'fefo' => $stocks->sortBy(fn (BinStock $s) => ($s->lot_number ?? 'zz'))->values(),
                default => $stocks->sortBy(fn (BinStock $s) => ($s->lot_number ?? 'aa'))->values(),
            };

            $remaining = $qty;
            $allocations = [];
            foreach ($stocks as $stock) {
                if ($remaining <= 0) {
                    break;
                }
                /** @var BinStock $locked */
                $locked = BinStock::query()->lockForUpdate()->findOrFail($stock->id);
                $take = min((float) $locked->qty, $remaining);
                if ($take <= 0) {
                    continue;
                }
                $locked->qty = bcsub((string) $locked->qty, (string) $take, 6);
                $locked->save();
                $allocations[] = ['lot' => $locked->lot_number, 'qty' => $take];
                $remaining -= $take;
            }

            if ($remaining > 0.000001) {
                throw new InvalidArgumentException(sprintf(
                    'Stok bin tidak mencukupi: kurang %.6f (metode %s).', $remaining, $method
                ));
            }

            return Task::create([
                'kind' => 'pick', 'status' => 'done', 'priority' => 'normal',
                'bin_id' => $bin->id, 'product_id' => $productId,
                'lot_number' => $lotNumber, 'qty' => $qty,
                'completed_at' => now(), 'created_by_user_id' => $operator->id,
            ]);
        });
    }

    // ── 41.3 Wave & tugas ───────────────────────────────────────────────

    public function createWave(string $strategy = 'fifo', ?string $notes = null): Wave
    {
        if (! in_array($strategy, ['fifo', 'fefo', 'zone', 'batch'], true)) {
            throw new InvalidArgumentException('Strategi wave tidak dikenal.');
        }

        $number = $this->numbering->nextNumber('WMS', 'WAVE', false, 'WAVE/{ENT}/');

        return Wave::create(['code' => $number, 'status' => 'open', 'strategy' => $strategy, 'notes' => $notes]);
    }

    /** Tambahkan tugas pick ke wave; wave open → released mengunci tugas. */
    public function addTaskToWave(Wave $wave, int $productId, float $qty, User $creator, ?Bin $bin = null, ?string $lotNumber = null): Task
    {
        if ($wave->status !== 'open') {
            throw new InvalidArgumentException('Tugas hanya dapat ditambahkan ke wave berstatus open.');
        }

        return Task::create([
            'kind' => 'pick', 'status' => 'open', 'priority' => 'normal',
            'bin_id' => $bin?->id, 'product_id' => $productId, 'lot_number' => $lotNumber,
            'qty' => $qty, 'wave_id' => $wave->id, 'created_by_user_id' => $creator->id,
        ]);
    }

    public function releaseWave(Wave $wave): Wave
    {
        return DB::transaction(function () use ($wave) {
            /** @var Wave $locked */
            $locked = Wave::query()->lockForUpdate()->findOrFail($wave->getKey());
            if ($locked->status === 'released') {
                return $locked;
            }
            if ($locked->status !== 'open') {
                throw new InvalidArgumentException("Wave {$locked->code} tidak dalam status open ({$locked->status}).");
            }
            if ($locked->tasks()->count() === 0) {
                throw new InvalidArgumentException('Wave kosong tidak dapat dirilis.');
            }

            $locked->update(['status' => 'released', 'released_at' => now()]);

            return $locked;
        });
    }

    public function closeTask(Task $task): Task
    {
        return DB::transaction(function () use ($task) {
            /** @var Task $locked */
            $locked = Task::query()->lockForUpdate()->findOrFail($task->getKey());
            if ($locked->status === 'done') {
                return $locked; // idempoten
            }

            $locked->update(['status' => 'done', 'completed_at' => now()]);

            return $locked;
        });
    }

    // ── 41.4 Transfer antar-gudang + cross-dock ─────────────────────────

    /**
     * @param  array<int, array{product_id: int, qty: float, lot_number?: string}>  $lines
     */
    public function createTransfer(Warehouse $from, Warehouse $to, array $lines, User $creator, bool $crossDock = false): Transfer
    {
        if ($from->id === $to->id) {
            throw new InvalidArgumentException('Gudang asal dan tujuan tidak boleh sama.');
        }
        if ($lines === []) {
            throw new InvalidArgumentException('Transfer harus berisi baris.');
        }

        return DB::transaction(function () use ($from, $to, $lines, $creator, $crossDock) {
            $number = $this->numbering->nextNumber('WMS', 'TRF', false, 'TRF/{ENT}/');

            $transfer = Transfer::create([
                'number' => $number, 'from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id,
                'status' => 'draft', 'cross_dock' => $crossDock,
                'notes' => null, 'created_by_user_id' => $creator->id,
            ]);

            foreach ($lines as $line) {
                TransferLine::create([
                    'transfer_id' => $transfer->id,
                    'product_id' => (int) $line['product_id'],
                    'lot_number' => $line['lot_number'] ?? null,
                    'qty' => (float) $line['qty'],
                    'status' => 'open',
                ]);
            }

            return $transfer->load('lines');
        });
    }

    /**
     * Ship: kurangi stok bin asal → catat baris; status in_transit
     * (akuntansi in-transit Resto tidak menyentuh ledger WMS — Fase 41
     * mengikuti pola: qty pindah antar-bin/gudang, nilai sah di ledger
     * diposting pemilik stok (Procurement/Resto/Manufacturing)).
     */
    public function shipTransfer(Transfer $transfer, User $operator): Transfer
    {
        return DB::transaction(function () use ($transfer, $operator) {
            /** @var Transfer $locked */
            $locked = Transfer::query()->lockForUpdate()->with('lines')->findOrFail($transfer->getKey());
            if ($locked->status === 'in_transit') {
                return $locked; // idempoten
            }
            if (! $locked->canTransitionTo('in_transit')) {
                throw new InvalidArgumentException("Transfer {$locked->number} tidak dapat dikirim ({$locked->status}).");
            }

            foreach ($locked->lines as $line) {
                if ($line->status !== 'open') {
                    continue;
                }
                // Kurangi dari bin asal manapun di gudang asal (FEFO).
                $this->pickFromWarehouse($locked->from_warehouse_id, (int) $line->product_id, (float) $line->qty, $operator, $line->lot_number);
                $line->update(['status' => 'picked']);
            }

            // 41.7 Integrasi Logistics: outbound DC → shipment otomatis
            // (idempoten per source_type+source_id; resi disimpan di transfer).
            if ($locked->tracking_number === null) {
                $from = Warehouse::find($locked->from_warehouse_id);
                $to = Warehouse::find($locked->to_warehouse_id);
                try {
                    $booked = $this->shipment->bookForOrder($operator, [
                        'origin_code' => (string) $from?->code,
                        'destination_address' => [
                            'street' => (string) ($to?->address ?? 'Gudang tujuan'),
                            'city' => (string) ($to?->city ?? 'Banjarmasin'),
                        ],
                        'consignee_name' => (string) $to?->name,
                        'consignee_phone' => '-',
                        'packages' => $locked->lines->map(fn (TransferLine $line) => [
                            'weight_g' => max(100, (int) ceil((float) $line->qty) * 1000),
                            'length_mm' => 300, 'width_mm' => 200, 'height_mm' => 200,
                            'description' => 'Transfer '.$locked->number,
                        ])->values()->all(),
                        'declared_value_idr' => max(1, $locked->lines->sum(fn (TransferLine $l) => (int) $l->qty)),
                        'source_type' => 'wms_transfer',
                        'source_id' => (string) $locked->id,
                        'amount_idr' => 1,
                    ]);
                    $locked->tracking_number = $booked['tracking_number'];
                } catch (\Throwable) {
                    // Logistics opsional: transfer tetap berjalan tanpa resi.
                }
            }

            $locked->update(['status' => 'in_transit']);

            return $locked->fresh('lines');
        });
    }

    /** Terima transfer: masukkan ke bin tujuan → status received. */
    public function receiveTransfer(Transfer $transfer, Bin $destinationBin, User $receiver): Transfer
    {
        return DB::transaction(function () use ($transfer, $destinationBin, $receiver) {
            /** @var Transfer $locked */
            $locked = Transfer::query()->lockForUpdate()->with('lines')->findOrFail($transfer->getKey());
            if ($locked->status === 'received') {
                return $locked; // idempoten
            }
            if (! $locked->canTransitionTo('received')) {
                throw new InvalidArgumentException("Transfer {$locked->number} tidak dapat diterima ({$locked->status}).");
            }

            foreach ($locked->lines as $line) {
                $this->putaway($destinationBin, (int) $line->product_id, (float) $line->qty, $receiver, $line->lot_number);
                $line->update(['status' => 'received']);
            }

            $locked->update(['status' => 'received']);

            return $locked->fresh('lines');
        });
    }

    /** Ambil qty dari semua bin pada suatu gudang (FEFO/FIFO). */
    private function pickFromWarehouse(string $warehouseId, int $productId, float $qty, User $operator, ?string $lotNumber = null): void
    {
        $remaining = $qty;
        $stocks = BinStock::join('wms_bins as b', 'b.id', '=', 'wms_bin_stocks.bin_id')
            ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
            ->join('wms_zones as z', 'z.id', '=', 'r.zone_id')
            ->where('z.warehouse_id', $warehouseId)
            ->where('wms_bin_stocks.product_id', $productId)
            ->where('wms_bin_stocks.status', 'available')
            ->when($lotNumber !== null, fn ($q) => $q->where('wms_bin_stocks.lot_number', $lotNumber))
            ->where('wms_bin_stocks.qty', '>', 0)
            ->orderBy('wms_bin_stocks.lot_number')
            ->get(['wms_bin_stocks.*']);

        foreach ($stocks as $stock) {
            if ($remaining <= 0) {
                break;
            }
            $take = min((float) $stock->qty, $remaining);
            if ($take <= 0) {
                continue;
            }
            BinStock::where('id', $stock->id)->decrement('qty', $take);
            $remaining -= $take;
        }

        if ($remaining > 0.000001) {
            throw new InvalidArgumentException(sprintf('Stok gudang asal tidak mencukupi (kurang %.6f).', $remaining));
        }
    }

    // ── 41.5 Cycle count ────────────────────────────────────────────────

    /**
     * Buat cycle count dari snapshot stok bin; hitung fisik diinput terpisah.
     *
     * @param  array<int, array{bin_id: int, product_id: int, counted_qty: float, lot_number?: string}>  $countedLines
     */
    public function startCycleCount(Warehouse $warehouse, array $countedLines, User $counter): CycleCount
    {
        if ($countedLines === []) {
            throw new InvalidArgumentException('Cycle count harus berisi baris hitung.');
        }

        return DB::transaction(function () use ($warehouse, $countedLines, $counter) {
            $number = $this->numbering->nextNumber('WMS', 'CC', false, 'CC/{ENT}/');

            $count = CycleCount::create([
                'number' => $number, 'warehouse_id' => $warehouse->id, 'status' => 'draft',
                'counted_by_user_id' => $counter->id,
            ]);

            $systemTotal = 0.0;
            $countedTotal = 0.0;
            foreach ($countedLines as $line) {
                $systemQty = (float) BinStock::where('bin_id', $line['bin_id'])
                    ->where('product_id', $line['product_id'])
                    ->when(isset($line['lot_number']), fn ($q) => $q->where('lot_number', $line['lot_number']))
                    ->sum('qty');
                $counted = (float) $line['counted_qty'];
                $variance = $counted - $systemQty;

                CycleCountLine::create([
                    'count_id' => $count->id, 'bin_id' => $line['bin_id'],
                    'product_id' => $line['product_id'],
                    'system_qty' => $systemQty, 'counted_qty' => $counted, 'variance_qty' => $variance,
                    'lot_number' => $line['lot_number'] ?? null,
                ]);

                $systemTotal += $systemQty;
                $countedTotal += $counted;
            }

            $accuracy = $systemTotal > 0
                ? max(0.0, (1 - abs($countedTotal - $systemTotal) / $systemTotal) * 100)
                : 100.0;

            $count->update([
                'system_qty' => $systemTotal, 'counted_qty' => $countedTotal,
                'variance_qty' => $countedTotal - $systemTotal,
                'accuracy_percent' => round($accuracy, 4),
            ]);

            return $count->load('lines');
        });
    }

    /** Ajukan penyesuaian (variance ≠ 0) ke approval four-eyes. */
    public function submitAdjustment(CycleCount $count, User $creator): CycleCount
    {
        return DB::transaction(function () use ($count, $creator) {
            /** @var CycleCount $locked */
            $locked = CycleCount::query()->lockForUpdate()->findOrFail($count->getKey());
            if ($locked->status !== 'draft') {
                throw new InvalidArgumentException("Cycle count {$locked->number} bukan draft ({$locked->status}).");
            }
            if (abs((float) $locked->variance_qty) < 0.000001) {
                throw new InvalidArgumentException('Tidak ada selisih — tidak perlu adjustment.');
            }

            $approval = $this->approvals->submit(
                approvalType: 'WMS_CYCLE_COUNT',
                title: "Cycle count {$locked->number} (selisih {$locked->variance_qty})",
                creator: $creator,
                approvable: $locked,
                steps: [['role' => 'admin']],
                slaHours: 48,
                metadata: ['count_id' => $locked->id, 'variance_qty' => (string) $locked->variance_qty],
            );

            $locked->update(['status' => 'pending_approval', 'approval_id' => (int) $approval->id]);

            return $locked;
        });
    }

    /** Setujui + terapkan penyesuaian ke stok bin (empat mata). */
    public function approveAndApply(CycleCount $count, User $approver): CycleCount
    {
        return DB::transaction(function () use ($count, $approver) {
            /** @var CycleCount $locked */
            $locked = CycleCount::query()->lockForUpdate()->with('lines')->findOrFail($count->getKey());
            if ($locked->status === 'applied') {
                return $locked; // idempoten
            }
            if ($locked->status !== 'pending_approval' || $locked->approval_id === null) {
                throw new InvalidArgumentException("Cycle count {$locked->number} tidak menunggu approval ({$locked->status}).");
            }

            $this->approvals->approve((int) $locked->approval_id, $approver, 'Penyesuaian cycle count');

            foreach ($locked->lines as $line) {
                $stock = BinStock::where('bin_id', $line->bin_id)
                    ->where('product_id', $line->product_id)
                    ->when($line->lot_number !== null, fn ($q) => $q->where('lot_number', $line->lot_number))
                    ->lockForUpdate()->first();
                if ($stock === null) {
                    $stock = BinStock::create([
                        'bin_id' => $line->bin_id, 'product_id' => $line->product_id,
                        'lot_number' => $line->lot_number, 'status' => 'available', 'qty' => 0,
                    ]);
                }

                $delta = (float) $line->variance_qty;
                if (abs($delta) < 0.000001) {
                    continue;
                }

                $stock->qty = bcadd((string) $stock->qty, (string) $delta, 6);
                if (bccomp((string) $stock->qty, '0', 6) < 0) {
                    throw new InvalidArgumentException('Penyesuaian membuat stok bin negatif — dibatalkan.');
                }
                $stock->save();

                // Selisih disinkronkan ke saldo produk global via InventoryService
                // (positif = tambah, negatif = kurang), alasan ADJUSTMENT.
                $productId = (int) $line->product_id;
                if ($delta > 0) {
                    // sourceId bertipe int pada InventoryService — referensi UUID
                    // cycle count dicatat di catatan (source_type menyimpan jenis).
                    $this->inventory->adjust(
                        $productId, (int) ceil($delta), StockMovementReason::ADJUSTMENT,
                        'wms_cycle_count', null, "Cycle count {$locked->number} ({$locked->id})", $approver->id
                    );
                } elseif ($delta < 0) {
                    $this->inventory->adjust(
                        $productId, -(int) floor(abs($delta)), StockMovementReason::ADJUSTMENT,
                        'wms_cycle_count', null, "Cycle count {$locked->number} ({$locked->id})", $approver->id
                    );
                }
            }

            $locked->update(['status' => 'applied']);

            return $locked->fresh('lines');
        });
    }

    // ── 41.6 Replenishment & slotting ───────────────────────────────────

    public function setPickFace(Bin $bin, int $productId, float $minQty, float $maxQty): Replenishment
    {
        $current = (float) BinStock::where('bin_id', $bin->id)
            ->where('product_id', $productId)->sum('qty');

        return Replenishment::updateOrCreate(
            ['bin_id' => $bin->id, 'product_id' => $productId],
            ['min_qty' => $minQty, 'max_qty' => $maxQty, 'current_qty' => $current, 'status' => 'open']
        );
    }

    /**
     * Hitung usulan replenishment untuk pick-face di bawah minimum.
     *
     * @return array<int, Replenishment>
     */
    public function computeReplenishment(): array
    {
        $results = [];
        foreach (Replenishment::all() as $rep) {
            $current = (float) BinStock::where('bin_id', $rep->bin_id)
                ->where('product_id', $rep->product_id)->sum('qty');
            $rep->current_qty = $current;
            $rep->suggested_qty = $rep->calculateSuggested();
            if ($rep->suggested_qty > 0 && $rep->status === 'open') {
                Task::firstOrCreate([
                    'kind' => 'replenish', 'status' => 'open', 'product_id' => $rep->product_id,
                    'bin_id' => $rep->bin_id,
                ], [
                    'priority' => 'normal', 'qty' => $rep->suggested_qty,
                    'source_ref' => 'replenish:'.$rep->id,
                ]);
            }
            $rep->save();
            $results[] = $rep;
        }

        return $results;
    }

    /**
     * Slotting ABC dari nilai penjualan tahunan per produk.
     *
     * @param  array<int, float>  $annualValueByProductId
     * @return array<int, Slotting>
     */
    public function computeSlotting(array $annualValueByProductId, ?string $zoneAPick = null): array
    {
        $grand = array_sum($annualValueByProductId);
        $results = [];
        foreach ($annualValueByProductId as $productId => $value) {
            $abc = Slotting::classify((float) $value, (float) $grand);
            $results[] = Slotting::updateOrCreate(
                ['product_id' => $productId],
                [
                    'abc_class' => $abc,
                    'annual_value_idr' => $value,
                    'suggested_zone_id' => ($abc === 'A' && $zoneAPick !== null) ? (int) $zoneAPick : null,
                ]
            );
        }

        return $results;
    }

    // ── 41.7 Dock appointment & packing list ────────────────────────────

    public function scheduleDock(Warehouse $warehouse, string $direction, string $reference, string $windowStart, string $windowEnd, ?string $carrier = null): DockAppointment
    {
        if (! in_array($direction, ['in', 'out'], true)) {
            throw new InvalidArgumentException('Arah dok harus in atau out.');
        }

        return DB::transaction(function () use ($warehouse, $direction, $reference, $windowStart, $windowEnd, $carrier) {
            $candidate = new DockAppointment([
                'warehouse_id' => $warehouse->id, 'direction' => $direction, 'reference' => $reference,
                'window_start' => now()->parse($windowStart), 'window_end' => now()->parse($windowEnd),
                'carrier' => $carrier,
            ]);

            $overlap = DockAppointment::where('warehouse_id', $warehouse->id)
                ->where('direction', $direction)
                ->where('status', '!=', 'cancelled')
                ->get()
                ->first(fn (DockAppointment $existing) => $candidate->overlaps($existing));

            if ($overlap !== null) {
                throw new InvalidArgumentException("Jendela dok bertabrakan dengan appointment {$overlap->reference}.");
            }

            $candidate->status = 'scheduled';
            $candidate->save();

            return $candidate;
        });
    }

    /**
     * Packing list + label resi: buat dari transfer (items = baris transfer).
     */
    public function createPackingList(Transfer $transfer, User $creator, ?string $trackingNumber = null): PackingList
    {
        $number = $this->numbering->nextNumber('WMS', 'PL', false, 'PL/{ENT}/');

        $items = $transfer->lines->map(fn (TransferLine $line) => [
            'product_id' => (int) $line->product_id,
            'qty' => (float) $line->qty,
            'lot_number' => $line->lot_number,
        ])->values()->all();

        return PackingList::create([
            'number' => $number, 'transfer_id' => $transfer->id,
            'items' => $items, 'tracking_number' => $trackingNumber,
            'status' => 'draft', 'created_by_user_id' => $creator->id,
        ]);
    }

    /** Cetak packing list → status printed; label resi sama dengan tracking_number. */
    public function markPackingListPrinted(PackingList $list): PackingList
    {
        return DB::transaction(function () use ($list) {
            /** @var PackingList $locked */
            $locked = PackingList::query()->lockForUpdate()->findOrFail($list->getKey());
            if ($locked->status === 'printed') {
                return $locked;
            }
            if ($locked->status !== 'draft') {
                throw new InvalidArgumentException("Packing list {$locked->number} bukan draft ({$locked->status}).");
            }

            $locked->update(['status' => 'printed']);

            return $locked;
        });
    }
}
