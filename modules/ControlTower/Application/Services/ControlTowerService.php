<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Illuminate\Support\Str;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\OrderPromise;

class ControlTowerService
{
    /**
     * 53.1 & 53.5 Visibilitas Multi-Eselon & Klasifikasi Material
     */
    public function recordEchelonStock(
        string $itemCode,
        string $itemName,
        string $node,
        int $onHand,
        int $inTransit,
        int $reserved,
        int $safetyStock,
        string $abc = 'A',
        string $xyz = 'X'
    ): EchelonStock {
        return EchelonStock::updateOrCreate(
            [
                'item_code' => $itemCode,
                'echelon_node' => strtoupper($node),
            ],
            [
                'item_name' => $itemName,
                'on_hand_qty' => $onHand,
                'in_transit_qty' => $inTransit,
                'reserved_qty' => $reserved,
                'safety_stock_qty' => $safetyStock,
                'abc_class' => strtoupper($abc),
                'xyz_class' => strtoupper($xyz),
            ]
        );
    }

    /**
     * 53.2 Peramalan Permintaan Multi-Model & Evaluasi MAPE
     */
    public function generateDemandForecast(
        string $itemCode,
        string $period,
        string $model,
        int $forecastQty
    ): DemandForecast {
        return DemandForecast::updateOrCreate(
            [
                'item_code' => $itemCode,
                'period' => $period,
                'algorithm_model' => strtoupper($model),
            ],
            [
                'forecast_code' => 'FC-'.strtoupper(Str::random(8)),
                'forecast_qty' => $forecastQty,
                'actual_qty' => null,
                'mape_percent' => null,
                'is_overridden' => false,
            ]
        );
    }

    public function recordActualDemandAndEvaluate(DemandForecast $forecast, int $actualQty): DemandForecast
    {
        $error = abs($actualQty - $forecast->forecast_qty);
        $mape = ($actualQty > 0) ? round(($error / $actualQty) * 100.0, 2) : 0.0;

        $forecast->update([
            'actual_qty' => $actualQty,
            'mape_percent' => $mape,
        ]);

        return $forecast;
    }

    /**
     * 53.4 Janji Pesanan ATP (Available-To-Promise) & CTP (Capable-To-Promise)
     */
    public function calculatePromise(
        string $orderRef,
        string $itemCode,
        int $requestedQty,
        string $targetDeliveryDate
    ): OrderPromise {
        // Ambil total stok bebas (on_hand - reserved) di DC
        $dcStock = EchelonStock::where('item_code', $itemCode)->where('echelon_node', 'DC')->first();
        $freeStock = $dcStock ? max(0, $dcStock->on_hand_qty - $dcStock->reserved_qty) : 0;

        $atpQty = min($requestedQty, $freeStock);
        $remainingNeeded = $requestedQty - $atpQty;

        // CTP: sisa kebutuhan diproduksi pabrik
        $ctpQty = $remainingNeeded;
        $status = ($atpQty + $ctpQty >= $requestedQty) ? 'confirmed' : 'partial';

        return OrderPromise::create([
            'promise_code' => 'PRM-'.strtoupper(Str::random(8)),
            'order_reference' => $orderRef,
            'item_code' => $itemCode,
            'requested_qty' => $requestedQty,
            'atp_confirmed_qty' => $atpQty,
            'ctp_manufacturing_qty' => $ctpQty,
            'promised_delivery_date' => $targetDeliveryDate,
            'promise_status' => $status,
        ]);
    }

    /**
     * 53.6 Manajemen Anomali & Blast Radius
     */
    public function triggerDisruptionAlert(
        string $severity,
        string $category,
        string $title,
        string $description,
        int $affectedOrdersCount
    ): DisruptionAlert {
        return DisruptionAlert::create([
            'alert_code' => 'ALT-'.strtoupper(Str::random(8)),
            'severity' => strtoupper($severity),
            'category' => strtoupper($category),
            'title' => $title,
            'description' => $description,
            'affected_orders_count' => $affectedOrdersCount,
            'status' => 'active',
        ]);
    }

    /**
     * 53.9 Audit Supply Chain Control Tower
     */
    public function auditControlTower(): array
    {
        $echelons = EchelonStock::count();
        $forecasts = DemandForecast::count();
        $promises = OrderPromise::count();
        $alerts = DisruptionAlert::count();

        // Invariant: reserved stock <= on hand + in transit
        $invalidStock = EchelonStock::whereRaw('reserved_qty > (on_hand_qty + in_transit_qty)')->count();

        // Invariant: ATP confirmed <= requested qty
        $invalidPromises = OrderPromise::whereColumn('atp_confirmed_qty', '>', 'requested_qty')->count();

        $discrepancyCount = $invalidStock + $invalidPromises;

        return [
            'status' => ($discrepancyCount === 0) ? 'OK' : 'DISCREPANCY',
            'discrepancy_count' => $discrepancyCount,
            'echelon_count' => $echelons,
            'forecast_count' => $forecasts,
            'promise_count' => $promises,
            'alert_count' => $alerts,
        ];
    }
}
