<?php

declare(strict_types=1);

namespace Modules\Vending\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Domain\Math\RouteOptimizer;
use Modules\Vending\Domain\Models\VendingAutoPo;
use Modules\Vending\Domain\Models\VendingRestockTask;
use Modules\Vending\Domain\Models\VendingUnit;

class VendingRestockService
{
    /**
     * Register a new smart vending machine unit.
     */
    public function registerUnit(array $data): VendingUnit
    {
        return VendingUnit::create([
            'unit_code' => $data['unit_code'],
            'location_name' => $data['location_name'],
            'latitude' => $data['latitude'] ?? 0,
            'longitude' => $data['longitude'] ?? 0,
            'current_stock' => $data['current_stock'] ?? 0,
            'max_capacity' => $data['max_capacity'] ?? 100,
            'critical_threshold' => $data['critical_threshold'] ?? 15,
            'status' => 'active',
            'total_sales_count' => 0,
            'total_sales_idr' => 0,
        ]);
    }

    /**
     * Check if unit stock is at or below critical threshold and trigger an Auto-PO.
     */
    public function checkAndTriggerAutoPo(
        int $unitId,
        string $itemCode,
        int $costPerUnitIdr = 8_000,
        int $dailyPlafonIdr = 15_000_000
    ): ?VendingAutoPo {
        $unit = VendingUnit::findOrFail($unitId);

        if ($unit->current_stock > $unit->critical_threshold) {
            return null;
        }

        $neededQty = max(10, $unit->max_capacity - $unit->current_stock);
        $totalEstimatedIdr = $neededQty * $costPerUnitIdr;
        $status = ($totalEstimatedIdr <= $dailyPlafonIdr) ? 'AUTO_APPROVED' : 'PENDING_APPROVAL';

        $autoPoCode = 'APO-'.strtoupper(substr(hash('sha256', "apo:{$unit->unit_code}:{$itemCode}:{$neededQty}"), 0, 10));

        return VendingAutoPo::updateOrCreate(
            ['auto_po_code' => $autoPoCode],
            [
                'outlet_code' => $unit->unit_code,
                'item_code' => $itemCode,
                'qty_ordered' => $neededQty,
                'estimated_total_idr' => $totalEstimatedIdr,
                'daily_plafon_idr' => $dailyPlafonIdr,
                'status' => $status,
            ]
        );
    }

    /**
     * Schedule a restock task if no pending/open task exists (anti-duplicate).
     */
    public function scheduleRestockTask(int $unitId): VendingRestockTask
    {
        $existing = VendingRestockTask::where('vending_unit_id', $unitId)
            ->whereIn('status', ['SCHEDULED', 'PICKED'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $unit = VendingUnit::findOrFail($unitId);
        $neededQty = max(1, $unit->max_capacity - $unit->current_stock);
        $taskCode = 'RST-'.strtoupper(substr(hash('sha256', "rst:{$unit->id}:{$neededQty}:".microtime(true).':'.mt_rand()), 0, 10));

        return VendingRestockTask::create([
            'task_code' => $taskCode,
            'vending_unit_id' => $unit->id,
            'suggested_qty' => $neededQty,
            'status' => 'SCHEDULED',
        ]);
    }

    /**
     * Complete restock task and increment unit stock.
     */
    public function completeRestockTask(int $taskId, int $replenishedQty): void
    {
        DB::transaction(function () use ($taskId, $replenishedQty): void {
            $task = VendingRestockTask::findOrFail($taskId);
            $unit = VendingUnit::where('id', $task->vending_unit_id)->lockForUpdate()->firstOrFail();

            $unit->current_stock = min($unit->max_capacity, $unit->current_stock + $replenishedQty);
            $unit->save();

            $task->status = 'COMPLETED';
            $task->save();
        });
    }

    /**
     * Report sensor health and transition status to MAINTENANCE if fault detected.
     */
    public function reportSensorStatus(int $unitId, array $sensorStatus): VendingUnit
    {
        $unit = VendingUnit::findOrFail($unitId);

        $hasFault = false;
        foreach ($sensorStatus as $sensor => $state) {
            if ($state === 'FAULT' || $state === 'JAMMED') {
                $hasFault = true;
                break;
            }
        }

        $unit->sensor_status = $sensorStatus;
        $unit->status = $hasFault ? 'MAINTENANCE' : 'active';
        $unit->save();

        return $unit;
    }

    /**
     * Plan optimal restocking route using RouteOptimizer (B0′.6).
     *
     * @param  list<int>  $unitIds
     * @return array{algorithm: string, ordered_unit_ids: list<int>, certificate: RouteOptimizationCertificate}
     */
    public function planRestockingRoute(array $unitIds): array
    {
        $n = count($unitIds);
        $units = VendingUnit::whereIn('id', $unitIds)->get()->keyBy('id');

        // Build symmetric distance matrix
        $dist = [];
        $ids = array_values($unitIds);

        for ($i = 0; $i < $n; $i++) {
            $dist[$i] = [];
            $u1 = $units->get($ids[$i]);
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    $dist[$i][$j] = 0;
                } else {
                    $u2 = $units->get($ids[$j]);
                    $latDiff = abs(($u1?->latitude ?? 0) - ($u2?->latitude ?? 0));
                    $lonDiff = abs(($u1?->longitude ?? 0) - ($u2?->longitude ?? 0));
                    // Manhattan / Grid distance in scaled meters
                    $dist[$i][$j] = max(100, (int) round(($latDiff + $lonDiff) / 100));
                }
            }
        }

        $result = RouteOptimizer::optimize($dist);

        // Map tour vertex indices to unit IDs (take unique vertices)
        $visitedIndices = [];
        foreach ($result->tour as $vertex) {
            if (! in_array($vertex, $visitedIndices, true) && isset($ids[$vertex])) {
                $visitedIndices[] = $vertex;
            }
        }

        $orderedUnitIds = array_map(fn (int $idx): int => $ids[$idx], $visitedIndices);
        $algorithm = ($n <= RouteOptimizer::EXACT_THRESHOLD) ? 'EXACT_TSP' : '2OPT_HEURISTIC';

        $certificate = new RouteOptimizationCertificate(
            certificate: $result->certificate,
            totalCostMeters: $result->cost,
            lowerBoundMeters: $result->lowerBound,
            isExact: $result->isExact
        );

        return [
            'algorithm' => $algorithm,
            'ordered_unit_ids' => $orderedUnitIds,
            'certificate' => $certificate,
        ];
    }
}
