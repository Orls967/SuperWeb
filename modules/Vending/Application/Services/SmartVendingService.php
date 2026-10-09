<?php

namespace Modules\Vending\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Vending\Domain\Models\VendingDemandForecast;
use Modules\Vending\Domain\Models\VendingRestockTask;
use Modules\Vending\Domain\Models\VendingTransaction;
use Modules\Vending\Domain\Models\VendingUnit;

class SmartVendingService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 75.1 Holt-Winters style Demand Forecasting & 75.3 Waste Forecasting
     */
    public function generateDemandAndWasteForecast(
        string $outletCode,
        string $itemCode,
        Carbon $forecastDate,
        array $historicalSales, // array of int
        int $footfallFactor = 100
    ): VendingDemandForecast {
        $n = count($historicalSales);
        if ($n === 0) {
            $baseForecast = 50;
        } else {
            // Simplified double exponential smoothing (level & trend)
            $alpha = 0.3;
            $beta = 0.1;
            $level = $historicalSales[0];
            $trend = ($historicalSales[$n - 1] - $historicalSales[0]) / max(1, $n - 1);

            for ($i = 0; $i < $n; $i++) {
                $lastLevel = $level;
                $level = $alpha * $historicalSales[$i] + (1 - $alpha) * ($level + $trend);
                $trend = $beta * ($level - $lastLevel) + (1 - $beta) * $trend;
            }
            $baseForecast = max(10, (int) round(($level + $trend) * ($footfallFactor / 100.0)));
        }

        // Projected waste based on display shelf pattern (approx 8% of forecast)
        $wasteForecast = (int) round($baseForecast * 0.08);

        return VendingDemandForecast::create([
            'forecast_code' => 'FC-'.strtoupper(bin2hex(random_bytes(6))),
            'outlet_code' => $outletCode,
            'item_code' => $itemCode,
            'forecast_date' => $forecastDate->toDateString(),
            'algorithm_model' => 'HOLT_WINTERS',
            'footfall_factor' => $footfallFactor,
            'forecast_qty' => $baseForecast,
            'waste_forecast_qty' => $wasteForecast,
            'suggest_scaledown' => false,
        ]);
    }

    /**
     * Record actual demand and waste, calculate MAPE
     */
    public function recordActualDemandAndWaste(VendingDemandForecast $forecast, int $actualDemand, int $actualWaste): VendingDemandForecast
    {
        $demandError = abs($forecast->forecast_qty - $actualDemand);
        $demandMape = $actualDemand > 0 ? round(($demandError / $actualDemand) * 100, 2) : 0.0;

        $wasteError = abs($forecast->waste_forecast_qty - $actualWaste);
        $wasteMape = $actualWaste > 0 ? round(($wasteError / $actualWaste) * 100, 2) : 0.0;

        // If actual waste > forecast waste by 20%, suggest scaledown for next batch
        $suggestScaledown = $actualWaste > ($forecast->waste_forecast_qty * 1.2);

        $forecast->update([
            'actual_qty' => $actualDemand,
            'mape_percent' => $demandMape,
            'waste_actual_qty' => $actualWaste,
            'waste_mape_percent' => $wasteMape,
            'suggest_scaledown' => $suggestScaledown,
        ]);

        return $forecast;
    }

    /**
     * 75.2 Auto-PO calculation with daily ceiling guardrail
     */
    public function generateAutoPo(
        string $outletCode,
        string $itemCode,
        int $forecastQty,
        int $currentStock,
        int $unitCostIdr,
        int $dailyPlafonIdr = 20_000_000
    ): array {
        $neededQty = max(0, $forecastQty - $currentStock);
        if ($neededQty === 0) {
            return [
                'status' => 'SKIPPED',
                'reason' => 'Stock sufficient',
            ];
        }

        $totalEstimatedIdr = $neededQty * $unitCostIdr;
        $isAutoApproved = $totalEstimatedIdr <= $dailyPlafonIdr;

        $autoPo = DB::table('ven_auto_pos')->insertGetId([
            'auto_po_code' => 'APO-'.strtoupper(bin2hex(random_bytes(6))),
            'outlet_code' => $outletCode,
            'item_code' => $itemCode,
            'qty_ordered' => $neededQty,
            'estimated_total_idr' => $totalEstimatedIdr,
            'daily_plafon_idr' => $dailyPlafonIdr,
            'status' => $isAutoApproved ? 'AUTO_APPROVED' : 'MANUAL_APPROVAL_REQUIRED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $autoPo,
            'qty_ordered' => $neededQty,
            'total_idr' => $totalEstimatedIdr,
            'auto_approved' => $isAutoApproved,
            'status' => $isAutoApproved ? 'AUTO_APPROVED' : 'MANUAL_APPROVAL_REQUIRED',
        ];
    }

    /**
     * 75.4 & 75.5 Process Vending Sale via QR / Biometric Face & Ledger Settlement
     */
    public function processVendingSale(
        VendingUnit $unit,
        string $itemCode,
        int $qty,
        int $priceIdr,
        string $paymentMethod = 'QRIS',
        ?string $biometricToken = null
    ): VendingTransaction {
        return DB::transaction(function () use ($unit, $itemCode, $qty, $priceIdr, $paymentMethod, $biometricToken) {
            /** @var VendingUnit $lockedUnit */
            $lockedUnit = VendingUnit::where('id', $unit->id)->lockForUpdate()->firstOrFail();

            if ($lockedUnit->current_stock < $qty) {
                throw new \RuntimeException('Insufficient stock in vending unit');
            }

            $totalPriceIdr = $priceIdr * $qty;
            $biometricHash = $biometricToken ? hash('sha256', $biometricToken) : null;

            // Decrement unit stock
            $lockedUnit->decrement('current_stock', $qty);
            $lockedUnit->increment('total_sales_count', $qty);
            $lockedUnit->increment('total_sales_idr', $totalPriceIdr);

            // Record transaction
            $tx = VendingTransaction::create([
                'tx_code' => 'VTX-'.strtoupper(bin2hex(random_bytes(6))),
                'vending_unit_id' => $lockedUnit->id,
                'item_code' => $itemCode,
                'qty' => $qty,
                'price_idr' => $totalPriceIdr,
                'payment_method' => $paymentMethod,
                'biometric_token_hash' => $biometricHash,
                'status' => 'COMPLETED',
            ]);

            // Ledger settlement: Debit Cash clearing (positive), Credit Vending revenue (negative)
            $this->ledgerService->post(new PostingDTO(
                type: 'VENDING_SALE',
                description: "Vending sale unit {$lockedUnit->unit_code} tx {$tx->tx_code}",
                idempotencyKey: "VEN-SALE-{$tx->tx_code}",
                entries: [
                    PostingEntryDTO::forCode('ven:clearing:IDR', 'IDR', $totalPriceIdr),
                    PostingEntryDTO::forCode('ven:sales_revenue:IDR', 'IDR', -$totalPriceIdr),
                ],
                referenceType: 'VENDING_SALE',
                referenceId: (string) $tx->id,
            ));

            // Check if stock fell below critical threshold -> auto-schedule restock task (no duplicate)
            if ($lockedUnit->current_stock <= $lockedUnit->critical_threshold) {
                $this->scheduleRestockTask($lockedUnit);
            }

            return $tx;
        });
    }

    /**
     * Schedule a restock task if no open task exists (anti-duplicate)
     */
    public function scheduleRestockTask(VendingUnit $unit): ?VendingRestockTask
    {
        $existing = VendingRestockTask::where('vending_unit_id', $unit->id)
            ->whereIn('status', ['SCHEDULED', 'PICKED'])
            ->first();

        if ($existing) {
            return $existing; // Avoid duplicate task
        }

        $neededQty = $unit->max_capacity - $unit->current_stock;

        return VendingRestockTask::create([
            'task_code' => 'RST-'.strtoupper(bin2hex(random_bytes(6))),
            'vending_unit_id' => $unit->id,
            'suggested_qty' => $neededQty,
            'status' => 'SCHEDULED',
        ]);
    }

    /**
     * Complete restock task
     */
    public function completeRestock(VendingRestockTask $task, int $replenishedQty): void
    {
        DB::transaction(function () use ($task, $replenishedQty) {
            $unit = VendingUnit::where('id', $task->vending_unit_id)->lockForUpdate()->firstOrFail();
            $unit->increment('current_stock', $replenishedQty);
            $task->update(['status' => 'COMPLETED']);
        });
    }

    /**
     * 75.6 Check sensor health and flag maintenance
     */
    public function reportSensorHealth(VendingUnit $unit, array $sensorStatus): VendingUnit
    {
        $isDefective = false;
        foreach ($sensorStatus as $sensor => $status) {
            if ($status === 'FAULT' || $status === 'JAMMED') {
                $isDefective = true;
                break;
            }
        }

        $unit->update([
            'sensor_status' => $sensorStatus,
            'status' => $isDefective ? 'MAINTENANCE' : 'ACTIVE',
        ]);

        return $unit;
    }
}
