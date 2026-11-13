<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Application\Actions\WmsLedgerPoster;
use Modules\Wms\Domain\Models\BinStock;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * X41-3: Manajemen karantina lot, pelepasan QA, dan write-off stok rusak/kadaluarsa (D41-8).
 */
final class LotQuarantineService
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly WmsLedgerPoster $poster,
    ) {}

    public function quarantineBinStock(int $binStockId, string $reason): BinStock
    {
        $stock = BinStock::findOrFail($binStockId);
        $stock->stock_status = 'quarantine';
        $stock->quarantine_reason = $reason;
        $stock->quarantined_at = $this->clock->now();
        $stock->save();

        return $stock;
    }

    public function releaseFromQuarantine(int $binStockId): BinStock
    {
        $stock = BinStock::findOrFail($binStockId);
        if ($stock->stock_status !== 'quarantine') {
            throw new DomainException("BinStock #{$binStockId} tidak dalam status quarantine.");
        }

        $stock->stock_status = 'available';
        $stock->released_at = $this->clock->now();
        $stock->save();

        return $stock;
    }

    /**
     * @return array{bin_stock_id: int, qty_written_off: int, value_written_off_idr: int}
     */
    public function writeOffBinStock(int $binStockId, int $unitCostIdr, string $reason): array
    {
        return DB::transaction(function () use ($binStockId, $unitCostIdr, $reason): array {
            $stock = BinStock::findOrFail($binStockId);
            $qtyBase = (int) ($stock->qty_base ?: QuantityNormalizer::fromDecimalString((string) $stock->qty));
            if ($qtyBase <= 0) {
                throw new DomainException("Tidak ada stok untuk di-write-off pada BinStock #{$binStockId}.");
            }

            $warehouseCode = (string) DB::table('wms_bins as b')
                ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
                ->join('wms_zones as z', 'z.id', '=', 'r.zone_id')
                ->join('wms_warehouses as w', 'w.id', '=', 'z.warehouse_id')
                ->where('b.id', $stock->bin_id)
                ->value('w.code');

            if ($warehouseCode === '') {
                $warehouseCode = Warehouse::first()?->code ?? 'DEFAULT';
            }

            $valueIdr = QuantityNormalizer::computeValueIdr($qtyBase, $unitCostIdr);
            $stock->qty_base = 0;
            $stock->qty = 0;
            $stock->stock_status = 'blocked';
            $stock->quarantine_reason = $reason;
            $stock->save();

            $this->poster->postInventoryWriteOff(
                warehouseCode: $warehouseCode,
                amountIdr: $valueIdr,
                referenceId: "WO-BS-{$binStockId}",
            );

            return [
                'bin_stock_id' => $binStockId,
                'qty_written_off' => $qtyBase,
                'value_written_off_idr' => $valueIdr,
            ];
        });
    }
}
