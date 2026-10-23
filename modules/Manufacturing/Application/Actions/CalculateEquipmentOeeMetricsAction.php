<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Actions;

use InvalidArgumentException;
use Modules\Manufacturing\Domain\Models\OeeSummary;
use Modules\Manufacturing\Domain\Models\WorkCenter;
use Modules\Shared\Application\BaseAction;

final class CalculateEquipmentOeeMetricsAction extends BaseAction
{
    /**
     * Calculates Availability, Performance, Quality, and overall OEE % for a work center.
     */
    public function execute(
        WorkCenter $workCenter,
        string $periodDate,
        string $shiftCode,
        int $plannedMinutes,
        int $runMinutes,
        int $idealCycleSeconds,
        float $totalQty,
        float $goodQty
    ): OeeSummary {
        if ($plannedMinutes <= 0 || $runMinutes < 0) {
            throw new InvalidArgumentException('Menit terencana harus lebih dari 0.');
        }

        if ($totalQty < 0.0 || $goodQty < 0.0 || $goodQty > $totalQty) {
            throw new InvalidArgumentException('Kuantitas output atau produk bagus tidak valid.');
        }

        return $this->transaction(function () use (
            $workCenter, $periodDate, $shiftCode, $plannedMinutes, $runMinutes,
            $idealCycleSeconds, $totalQty, $goodQty
        ) {
            // Availability: Run Time / Planned Time
            $availability = min(100.0, round(($runMinutes / $plannedMinutes) * 100, 4));

            // Performance: (Ideal Cycle Time * Total Count) / Run Time
            $runSeconds = max(1, $runMinutes * 60);
            $producedStandardSeconds = $idealCycleSeconds * $totalQty;
            $performance = $runMinutes > 0
                ? min(100.0, round(($producedStandardSeconds / $runSeconds) * 100, 4))
                : 0.0;

            // Quality: Good Parts / Total Parts
            $quality = $totalQty > 0
                ? min(100.0, round(($goodQty / $totalQty) * 100, 4))
                : 100.0;

            // Overall OEE %
            $oee = round(($availability * $performance * $quality) / 10000, 4);

            $summary = OeeSummary::updateOrCreate(
                [
                    'work_center_id' => $workCenter->id,
                    'period_date' => $periodDate,
                    'shift_code' => $shiftCode,
                ],
                [
                    'planned_minutes' => $plannedMinutes,
                    'run_minutes' => $runMinutes,
                    'ideal_cycle_seconds' => $idealCycleSeconds,
                    'qty_total' => $totalQty,
                    'qty_good' => $goodQty,
                    'availability_percent' => $availability,
                    'performance_percent' => $performance,
                    'quality_percent' => $quality,
                    'oee_percent' => $oee,
                ]
            );

            $this->audit('mfg.oee_metrics_calculated', $summary, null, [
                'work_center_id' => $workCenter->id,
                'oee_percent' => $oee,
                'availability' => $availability,
                'performance' => $performance,
                'quality' => $quality,
            ]);

            return $summary;
        });
    }
}
