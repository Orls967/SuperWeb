<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Domain\Models\MiningHaulCycle;
use Modules\Mining\Domain\Models\MiningPlanShift;

final class GenerateShiftProductionReportAction
{
    /**
     * Menghasilkan laporan rekapitulasi produksi shift dan ekspor CSV.
     *
     * @param string $shiftDate YYYY-MM-DD
     * @param string $shiftCode SHIFT_1, SHIFT_2, SHIFT_3
     * @return array{
     *     summary: array{
     *         shift_date: string,
     *         shift_code: string,
     *         total_cycles: int,
     *         total_payload_ton: float,
     *         total_distance_km: float,
     *         target_tonnage_ton: float,
     *         achievement_pct: float
     *     },
     *     csv_content: string
     * }
     */
    public function execute(string $shiftDate, string $shiftCode): array
    {
        $cycles = MiningHaulCycle::whereDate('cycle_date', $shiftDate)
            ->where('shift', $shiftCode)
            ->with(['truck', 'shovel', 'pit'])
            ->get();

        $planShift = MiningPlanShift::whereDate('shift_date', $shiftDate)
            ->where('shift_code', $shiftCode)
            ->first();

        $totalCycles = $cycles->count();
        $totalPayloadTon = (float) $cycles->sum('payload_ton');
        $totalDistanceKm = (float) $cycles->sum('haul_distance_km');
        $targetTon = $planShift ? ($planShift->target_tonnage_kg / 1000.0) : 1000.0;
        $achievePct = $targetTon > 0 ? round(($totalPayloadTon / $targetTon) * 100, 2) : 0.0;

        $csvRows = [];
        $csvRows[] = 'cycle_code,truck_code,shovel_code,pit_id,payload_ton,haul_distance_km,status';

        foreach ($cycles as $c) {
            $csvRows[] = sprintf(
                '%s,%s,%s,%s,%.2f,%.2f,%s',
                $c->cycle_code,
                $c->truck?->code ?? '-',
                $c->shovel?->code ?? '-',
                $c->pit_id,
                $c->payload_ton,
                $c->haul_distance_km,
                $c->status->value
            );
        }

        return [
            'summary' => [
                'shift_date' => $shiftDate,
                'shift_code' => $shiftCode,
                'total_cycles' => $totalCycles,
                'total_payload_ton' => $totalPayloadTon,
                'total_distance_km' => $totalDistanceKm,
                'target_tonnage_ton' => $targetTon,
                'achievement_pct' => $achievePct,
            ],
            'csv_content' => implode("\n", $csvRows),
        ];
    }
}
