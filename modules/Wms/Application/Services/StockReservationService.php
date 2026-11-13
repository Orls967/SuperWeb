<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Contracts\StockAvailabilityReader;
use Modules\Wms\Domain\Models\StockReservation;
use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * X41-2: Layanan reservasi stok dengan TTL deterministik & pembaca ketersediaan stok.
 */
final class StockReservationService implements StockAvailabilityReader
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    public function getAvailableQty(int $productId, string|int|null $warehouseId = null): int
    {
        if ($warehouseId !== null) {
            return $this->getBreakdown($productId, $warehouseId)['available_base'];
        }

        $warehouseIds = DB::table('wms_warehouses')->pluck('id');
        $total = 0;
        foreach ($warehouseIds as $whId) {
            $total += $this->getBreakdown($productId, $whId)['available_base'];
        }

        return $total;
    }

    public function getBreakdown(int $productId, string|int $warehouseId): array
    {
        $stocks = DB::table('wms_bin_stocks as bs')
            ->join('wms_bins as b', 'b.id', '=', 'bs.bin_id')
            ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
            ->join('wms_zones as z', 'z.id', '=', 'r.zone_id')
            ->where('z.warehouse_id', $warehouseId)
            ->where('bs.product_id', $productId)
            ->select('bs.qty_base', 'bs.qty', 'bs.stock_status')
            ->get();

        $onHand = 0;
        $quarantined = 0;

        foreach ($stocks as $row) {
            $qty = (int) ($row->qty_base ?: QuantityNormalizer::fromDecimalString((string) $row->qty));
            if ($qty <= 0) {
                continue;
            }
            $onHand += $qty;
            if (in_array((string) $row->stock_status, ['quarantine', 'blocked'], true)) {
                $quarantined += $qty;
            }
        }

        $now = $this->clock->now();
        $reserved = (int) DB::table('wms_stock_reservations')
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('status', StockReservation::STATUS_ACTIVE)
            ->where('expires_at', '>', $now)
            ->sum('qty_base');

        $available = max(0, $onHand - $quarantined - $reserved);

        return [
            'on_hand_base' => $onHand,
            'reserved_base' => $reserved,
            'quarantined_base' => $quarantined,
            'available_base' => $available,
        ];
    }

    public function reserve(
        string|int $warehouseId,
        int $productId,
        int $qtyBase,
        string $referenceType,
        string $referenceId,
        int $ttlMinutes = 30,
        string|int|null $binId = null,
        ?string $lotNumber = null,
        ?int $actorUserId = null,
    ): StockReservation {
        if ($qtyBase <= 0 || $ttlMinutes <= 0) {
            throw new InvalidArgumentException('Kuantitas reservasi dan TTL harus lebih dari 0.');
        }

        return DB::transaction(function () use (
            $warehouseId,
            $productId,
            $qtyBase,
            $referenceType,
            $referenceId,
            $ttlMinutes,
            $binId,
            $lotNumber,
            $actorUserId
        ): StockReservation {
            $available = $this->getAvailableQty($productId, $warehouseId);
            if ($available < $qtyBase) {
                throw new DomainException("Stok tersedia tidak cukup: tersedia {$available}, diminta {$qtyBase}.");
            }

            return StockReservation::create([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'bin_id' => $binId,
                'lot_number' => $lotNumber,
                'qty_base' => $qtyBase,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'status' => StockReservation::STATUS_ACTIVE,
                'expires_at' => $this->clock->now()->addMinutes($ttlMinutes),
                'created_by_user_id' => $actorUserId,
            ]);
        });
    }

    public function commit(int $reservationId): StockReservation
    {
        $res = StockReservation::findOrFail($reservationId);
        if ($res->status !== StockReservation::STATUS_ACTIVE) {
            throw new DomainException("Reservasi #{$reservationId} tidak berstatus active.");
        }
        $res->status = StockReservation::STATUS_COMMITTED;
        $res->save();

        return $res;
    }

    public function release(int $reservationId): StockReservation
    {
        $res = StockReservation::findOrFail($reservationId);
        if ($res->status !== StockReservation::STATUS_ACTIVE) {
            return $res;
        }
        $res->status = StockReservation::STATUS_RELEASED;
        $res->save();

        return $res;
    }

    public function expireStaleReservations(): int
    {
        $now = $this->clock->now();

        return StockReservation::where('status', StockReservation::STATUS_ACTIVE)
            ->where('expires_at', '<=', $now)
            ->update(['status' => StockReservation::STATUS_EXPIRED]);
    }
}
