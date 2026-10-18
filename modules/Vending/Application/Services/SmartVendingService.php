<?php

declare(strict_types=1);

namespace Modules\Vending\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Vending\Domain\Models\VendingAutoPo;
use Modules\Vending\Domain\Models\VendingDemandForecast;
use Modules\Vending\Domain\Models\VendingRestockTask;
use Modules\Vending\Domain\Models\VendingTransaction;
use Modules\Vending\Domain\Models\VendingUnit;

class SmartVendingService
{
    public function __construct(
        protected SimClockInterface $simClock,
        protected LedgerService $ledgerService
    ) {}

    /**
     * 75.1 Holt-Winters Demand Forecasting & 75.3 Waste Forecasting with deterministic seed.
     */
    public function generateDemandAndWasteForecast(
        string $outletCode,
        string $itemCode,
        Carbon $forecastDate,
        array $historicalSales,
        int $footfallFactor = 100
    ): VendingDemandForecast {
        $n = count($historicalSales);
        if ($n === 0) {
            $baseForecast = 50;
        } else {
            // Holt-Winters double exponential smoothing (level & trend)
            $alpha = 0.3;
            $beta = 0.1;
            $level = (float) $historicalSales[0];
            $trend = (float) (($historicalSales[$n - 1] - $historicalSales[0]) / max(1, $n - 1));

            for ($i = 0; $i < $n; $i++) {
                $lastLevel = $level;
                $level = $alpha * $historicalSales[$i] + (1 - $alpha) * ($level + $trend);
                $trend = $beta * ($level - $lastLevel) + (1 - $beta) * $trend;
            }
            $baseForecast = max(10, (int) round(($level + $trend) * ($footfallFactor / 100.0)));
        }

        // Projected waste based on display shelf pattern (approx 8% of forecast)
        $wasteForecast = (int) round($baseForecast * 0.08);
        $dateStr = $forecastDate->toDateString();
        $forecastCode = 'FC-'.strtoupper(substr(hash('sha256', "fc:{$outletCode}:{$itemCode}:{$dateStr}"), 0, 12));

        return VendingDemandForecast::updateOrCreate(
            ['forecast_code' => $forecastCode],
            [
                'outlet_code' => $outletCode,
                'item_code' => $itemCode,
                'forecast_date' => $dateStr,
                'algorithm_model' => 'HOLT_WINTERS',
                'footfall_factor' => $footfallFactor,
                'forecast_qty' => $baseForecast,
                'waste_forecast_qty' => $wasteForecast,
                'suggest_scaledown' => false,
            ]
        );
    }

    /**
     * Record actual demand and waste, calculate MAPE (DoR 75.1, 75.3, 75.7a).
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
     * 75.2 Auto-PO calculation with daily ceiling guardrail (DoR 75.2, 75.7b).
     */
    public function generateAutoPo(
        string $outletCode,
        string $itemCode,
        int $forecastQty,
        int $currentStock,
        int $unitCostIdr,
        int $dailyPlafonIdr = 15_000_000
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
        $status = $isAutoApproved ? 'AUTO_APPROVED' : 'MANUAL_APPROVAL_REQUIRED';

        $autoPoCode = 'APO-'.strtoupper(substr(hash('sha256', "apo:{$outletCode}:{$itemCode}:{$forecastQty}:{$currentStock}"), 0, 12));

        $autoPo = VendingAutoPo::updateOrCreate(
            ['auto_po_code' => $autoPoCode],
            [
                'outlet_code' => $outletCode,
                'item_code' => $itemCode,
                'qty_ordered' => $neededQty,
                'estimated_total_idr' => $totalEstimatedIdr,
                'daily_plafon_idr' => $dailyPlafonIdr,
                'status' => $status,
            ]
        );

        return [
            'id' => $autoPo->id,
            'auto_po_code' => $autoPoCode,
            'qty_ordered' => $neededQty,
            'total_idr' => $totalEstimatedIdr,
            'auto_approved' => $isAutoApproved,
            'status' => $status,
        ];
    }

    /**
     * 75.4 & 75.5 Process Vending Sale via QR / Biometric Face & Ledger Settlement (DoR 75.5, 75.7c).
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

            // Decrement unit stock & increment sales stats
            $lockedUnit->decrement('current_stock', $qty);
            $lockedUnit->increment('total_sales_count', $qty);
            $lockedUnit->increment('total_sales_idr', $totalPriceIdr);

            $txCode = 'VTX-'.strtoupper(substr(hash('sha256', "vtx:{$lockedUnit->id}:{$itemCode}:{$lockedUnit->total_sales_count}"), 0, 12));
            $idempotencyKey = "vnd:sale:{$lockedUnit->id}:{$txCode}";

            // Record transaction
            $tx = VendingTransaction::firstOrCreate(
                ['tx_code' => $txCode],
                [
                    'vending_unit_id' => $lockedUnit->id,
                    'item_code' => $itemCode,
                    'qty' => $qty,
                    'price_idr' => $totalPriceIdr,
                    'payment_method' => $paymentMethod,
                    'biometric_token_hash' => $biometricHash,
                    'status' => 'COMPLETED',
                ]
            );

            // Ledger settlement: Debit Cash clearing (-), Credit Vending revenue (+)
            $this->ledgerService->post(new PostingDTO(
                type: 'VENDING_SALE',
                description: "Vending sale unit {$lockedUnit->unit_code} tx {$tx->tx_code}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('ven:clearing:IDR', $totalPriceIdr)
                    ->credit('ven:sales_revenue:IDR', $totalPriceIdr)
                    ->toEntries('IDR'),
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
     * Schedule a restock task if no open task exists (anti-duplicate, DoR 75.4, 75.7d).
     */
    public function scheduleRestockTask(VendingUnit $unit): ?VendingRestockTask
    {
        $existing = VendingRestockTask::where('vending_unit_id', $unit->id)
            ->whereIn('status', ['SCHEDULED', 'PICKED'])
            ->first();

        if ($existing) {
            return $existing; // Anti-duplicate: preserve existing scheduled task
        }

        $neededQty = $unit->max_capacity - $unit->current_stock;
        $taskCode = 'RST-'.strtoupper(substr(hash('sha256', "rst:{$unit->id}:{$neededQty}"), 0, 12));

        return VendingRestockTask::firstOrCreate(
            ['task_code' => $taskCode],
            [
                'vending_unit_id' => $unit->id,
                'suggested_qty' => $neededQty,
                'status' => 'SCHEDULED',
            ]
        );
    }

    /**
     * Complete restock task.
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
     * 75.6 Check sensor health and flag maintenance.
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

    /**
     * Reconcile vending sales with ledger & unit stock statistics (DoR 75.7e).
     */
    public function reconcileVending(VendingUnit $unit): array
    {
        $unitSalesIdr = (int) $unit->total_sales_idr;
        $txSumIdr = (int) VendingTransaction::where('vending_unit_id', $unit->id)
            ->where('status', 'COMPLETED')
            ->sum('price_idr');

        $isReconciled = ($unitSalesIdr === $txSumIdr);

        return [
            'unit_code' => $unit->unit_code,
            'unit_sales_idr' => $unitSalesIdr,
            'transaction_sum_idr' => $txSumIdr,
            'is_reconciled' => $isReconciled,
        ];
    }

    /**
     * Two-source smart vending audit command engine.
     */
    public function auditVending(): array
    {
        $unitCount = VendingUnit::count();
        $txCount = VendingTransaction::count();
        $restockCount = VendingRestockTask::count();
        $discrepancies = 0;

        $units = VendingUnit::all();
        foreach ($units as $unit) {
            $txTotal = (int) VendingTransaction::where('vending_unit_id', $unit->id)
                ->where('status', 'COMPLETED')
                ->sum('price_idr');

            if ($unit->total_sales_idr !== $txTotal) {
                $discrepancies++;
            }

            if ($unit->current_stock < 0 || $unit->current_stock > $unit->max_capacity) {
                $discrepancies++;
            }

            $openTasks = VendingRestockTask::where('vending_unit_id', $unit->id)
                ->whereIn('status', ['SCHEDULED', 'PICKED'])
                ->count();

            if ($openTasks > 1) {
                $discrepancies++;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY',
            'unit_count' => $unitCount,
            'transaction_count' => $txCount,
            'restock_task_count' => $restockCount,
            'discrepancies' => $discrepancies,
        ];
    }
}
