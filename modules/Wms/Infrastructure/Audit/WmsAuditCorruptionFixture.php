<?php

declare(strict_types=1);

namespace Modules\Wms\Infrastructure\Audit;

use Illuminate\Support\Facades\DB;

/**
 * X41-5: Fixture deterministik untuk menguji bahwa `wms:audit` mendeteksi korupsi data/ledger.
 * Hanya digunakan pada skenario pengujian sabotase (L.5).
 */
final class WmsAuditCorruptionFixture
{
    public static function injectNegativeBinStock(int $binId, int $productId): void
    {
        DB::table('wms_bin_stocks')->insert([
            'bin_id' => $binId,
            'product_id' => $productId,
            'qty' => -5,
            'qty_base' => -5,
            'stock_status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function injectUnbalancedUnitCostMismatch(int $productId, int $warehouseId): void
    {
        DB::table('wms_unit_costs')->insert([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'cost_idr' => 999999,
            'method' => 'std',
            'effective_date' => '2026-11-13',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
