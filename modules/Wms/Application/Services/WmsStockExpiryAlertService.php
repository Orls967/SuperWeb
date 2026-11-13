<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\Services\ShelfLifeGuard;
use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * Mengelompokkan stok aktif berdasarkan horizon kedaluwarsa & menghitung eksposur nilai IDR.
 */
final class WmsStockExpiryAlertService
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly ShelfLifeGuard $shelfLifeGuard = new ShelfLifeGuard,
    ) {}

    /**
     * @return array{
     *     expired_qty: int,
     *     near_expiry_qty: int,
     *     fresh_qty: int,
     *     at_risk_value_idr: int
     * }
     */
    public function summarizeExposure(int $minSellableDays = 30): array
    {
        $today = $this->clock->now();
        $rows = DB::table('wms_bin_stocks')
            ->where('qty_base', '>', 0)
            ->select('product_id', 'qty_base', 'expiry_date')
            ->get();

        $expiredQty = 0;
        $nearQty = 0;
        $freshQty = 0;
        $atRiskValue = 0;

        foreach ($rows as $row) {
            $qty = (int) $row->qty_base;
            $inspection = $this->shelfLifeGuard->inspect($today, $row->expiry_date, $minSellableDays);
            $cost = (int) (DB::table('wms_unit_costs')
                ->where('product_id', $row->product_id)
                ->where('is_active', true)
                ->value('cost_idr') ?? 0);

            if ($inspection['status'] === 'expired') {
                $expiredQty += $qty;
                $atRiskValue += QuantityNormalizer::computeValueIdr($qty, $cost);
            } elseif ($inspection['status'] === 'near_expiry') {
                $nearQty += $qty;
                $atRiskValue += QuantityNormalizer::computeValueIdr($qty, $cost);
            } else {
                $freshQty += $qty;
            }
        }

        return [
            'expired_qty' => $expiredQty,
            'near_expiry_qty' => $nearQty,
            'fresh_qty' => $freshQty,
            'at_risk_value_idr' => $atRiskValue,
        ];
    }
}
