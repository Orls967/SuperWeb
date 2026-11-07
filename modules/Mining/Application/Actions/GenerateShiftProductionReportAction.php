<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Domain\Enums\HaulCycleStatus;
use Modules\Mining\Domain\Models\MiningHaulCycle;
use Modules\Mining\Domain\Models\MiningPlanShift;

/**
 * Menghasilkan laporan rekapitulasi produksi shift tambang (integer kg, meter, bps)
 * beserta baris tabel siap ekspor CSV (D93-2, D93-8).
 */
final class GenerateShiftProductionReportAction
{
    /**
     * @param  string  $shiftDate  YYYY-MM-DD
     * @param  string  $shiftCode  SHIFT_A, SHIFT_B, SHIFT_C
     * @return array{
     *     summary: array{
     *         shift_date: string,
     *         shift_code: string,
     *         total_cycles: int,
     *         completed_cycles: int,
     *         aborted_cycles: int,
     *         total_target_payload_kg: int,
     *         total_actual_payload_kg: int,
     *         payload_variance_kg: int,
     *         total_distance_meters: int,
     *         total_fuel_consumed_ml: int,
     *         shift_target_tonnage_kg: int,
     *         achievement_bps: int
     *     },
     *     header: list<string>,
     *     rows: list<list<int|string>>
     * }
     */
    public function execute(string $shiftDate, string $shiftCode): array
    {
        $cycles = MiningHaulCycle::query()
            ->whereDate('shift_date', $shiftDate)
            ->where('shift_code', $shiftCode)
            ->with(['truck', 'shovel', 'pit'])
            ->orderBy('cycle_seq')
            ->orderBy('id')
            ->get();

        $planShift = MiningPlanShift::query()
            ->whereDate('shift_date', $shiftDate)
            ->where('shift_code', $shiftCode)
            ->orderBy('id')
            ->first();

        $totalCycles = $cycles->count();
        $completedCycles = 0;
        $abortedCycles = 0;
        $totalTargetKg = 0;
        $totalActualKg = 0;
        $totalDistanceMeters = 0;
        $totalFuelMl = 0;

        $rows = [];
        foreach ($cycles as $cycle) {
            $statusVal = $cycle->status instanceof HaulCycleStatus
                ? $cycle->status->value
                : (string) $cycle->status;

            if ($statusVal === HaulCycleStatus::COMPLETED->value) {
                $completedCycles++;
            } elseif ($statusVal === HaulCycleStatus::ABORTED->value) {
                $abortedCycles++;
            }

            $targetKg = (int) $cycle->target_payload_kg;
            $actualKg = (int) $cycle->actual_payload_kg;
            $distM = (int) $cycle->distance_meters;
            $fuelMl = (int) $cycle->fuel_consumed_ml;

            $totalTargetKg += $targetKg;
            $totalActualKg += $actualKg;
            $totalDistanceMeters += $distM;
            $totalFuelMl += $fuelMl;

            $rows[] = [
                (string) $cycle->id,
                (int) $cycle->cycle_seq,
                (string) ($cycle->truck?->equipment_code ?? '-'),
                (string) ($cycle->shovel?->equipment_code ?? '-'),
                (string) ($cycle->pit?->pit_code ?? $cycle->pit_id),
                $targetKg,
                $actualKg,
                $actualKg - $targetKg,
                $distM,
                $fuelMl,
                $statusVal,
            ];
        }

        $shiftTargetKg = $planShift ? (int) $planShift->target_tonnage_kg : max(100000, $totalTargetKg);
        $achievementBps = $shiftTargetKg > 0 ? intdiv($totalActualKg * 10000, $shiftTargetKg) : 0;

        return [
            'summary' => [
                'shift_date' => $shiftDate,
                'shift_code' => $shiftCode,
                'total_cycles' => $totalCycles,
                'completed_cycles' => $completedCycles,
                'aborted_cycles' => $abortedCycles,
                'total_target_payload_kg' => $totalTargetKg,
                'total_actual_payload_kg' => $totalActualKg,
                'payload_variance_kg' => $totalActualKg - $totalTargetKg,
                'total_distance_meters' => $totalDistanceMeters,
                'total_fuel_consumed_ml' => $totalFuelMl,
                'shift_target_tonnage_kg' => $shiftTargetKg,
                'achievement_bps' => $achievementBps,
            ],
            'header' => [
                'cycle_id',
                'cycle_seq',
                'truck_code',
                'shovel_code',
                'pit_code',
                'target_payload_kg',
                'actual_payload_kg',
                'variance_kg',
                'distance_meters',
                'fuel_consumed_ml',
                'status',
            ],
            'rows' => $rows,
        ];
    }
}
