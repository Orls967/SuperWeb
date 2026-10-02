<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Services;

use Illuminate\Support\Collection;
use Modules\Logistics\Domain\Models\Shipment;

/**
 * Laporan margin per resi: pendapatan freight yang sudah diakui dikurangi biaya carrier yang sudah diakrual.
 */
class ShipmentMarginReport
{
    /**
     * @return array{shipment: Shipment, revenue_idr: int, carrier_cost_idr: int, margin_idr: int, margin_pct: float|null}
     */
    public function forShipment(Shipment $shipment): array
    {
        $shipment->loadMissing('legs');

        $revenue = $shipment->revenue_recognized_at !== null ? (int) $shipment->total_amount_idr : 0;
        $cost = (int) $shipment->legs->whereNotNull('cost_accrued_at')->sum('carrier_cost_idr');
        $margin = $revenue - $cost;

        return [
            'shipment' => $shipment,
            'revenue_idr' => $revenue,
            'carrier_cost_idr' => $cost,
            'margin_idr' => $margin,
            'margin_pct' => $revenue > 0 ? round($margin / $revenue * 100, 2) : null,
        ];
    }

    /**
     * @return Collection<int, array{shipment: Shipment, revenue_idr: int, carrier_cost_idr: int, margin_idr: int, margin_pct: float|null}>
     */
    public function recent(int $limit = 50): Collection
    {
        return Shipment::whereNotNull('revenue_recognized_at')
            ->with('legs')
            ->latest('revenue_recognized_at')
            ->limit($limit)
            ->get()
            ->map(fn (Shipment $s) => $this->forShipment($s));
    }
}
