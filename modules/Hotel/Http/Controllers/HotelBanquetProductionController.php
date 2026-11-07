<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Domain\Models\MiceContract;

class HotelBanquetProductionController
{
    public function index(Request $request): View
    {
        $contracts = MiceContract::latest()->take(10)->get();

        return view('hotel::hotel_banquet_production_sheet', [
            'contracts' => $contracts,
            'defaultPaxCount' => 450,
            'estimatedBomIdr' => 78500000,
        ]);
    }

    public function explodeBom(Request $request, string $contractId): JsonResponse
    {
        $contract = MiceContract::findOrFail($contractId);
        $pax = (int) ($contract->pax_count ?? 100);

        // Standar simulasi ledakan BOM dapur
        $items = [
            [
                'name' => 'Wagyu Beef Tenderloin',
                'category' => 'Butchery',
                'net_qty_kg' => round($pax * 0.20, 1),
                'yield_loss_pct' => 12.0,
                'gross_qty_kg' => round(($pax * 0.20) / 0.88, 1),
                'unit_cost_idr' => 480000,
                'subtotal_idr' => (int) round((($pax * 0.20) / 0.88) * 480000),
            ],
            [
                'name' => 'Truffle Paste Sauce',
                'category' => 'Sauces & Spices',
                'net_qty_kg' => round($pax * 0.033, 1),
                'yield_loss_pct' => 2.0,
                'gross_qty_kg' => round(($pax * 0.033) / 0.98, 1),
                'unit_cost_idr' => 650000,
                'subtotal_idr' => (int) round((($pax * 0.033) / 0.98) * 650000),
            ],
        ];

        $totalHpp = array_sum(array_column($items, 'subtotal_idr'));
        $revenue = (int) ($contract->contract_value_idr ?? 150000000);
        $marginPct = $revenue > 0 ? round((($revenue - $totalHpp) / $revenue) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'contract_id' => $contractId,
            'pax_count' => $pax,
            'bom_items' => $items,
            'total_hpp_idr' => $totalHpp,
            'gross_margin_pct' => $marginPct,
            'is_margin_safe' => $marginPct >= 40.0,
        ]);
    }
}
