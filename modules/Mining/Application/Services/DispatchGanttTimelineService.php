<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Modules\Mining\Domain\Models\MiningHaulCycle;

/**
 * Papan Gantt siklus angkut per shift (D93-2). Interval tiap tahap diturunkan dari riwayat
 * transisi append-only min_haul_cycle_histories, bukan dari durasi yang dikarang.
 * Kompleksitas: O(C log C) untuk C siklus pada shift (sort riwayat).
 */
final class DispatchGanttTimelineService
{
    /**
     * @return array{
     *     rows: list<array{truck: string, shovel: string, cycle_seq: int, status: string, intervals: list<array{stage: string, start: string, end: string, minutes: int}>}>,
     *     truck_wait_minutes: int,
     *     shovel_idle_minutes: int
     * }
     */
    public function build(string $shiftDate, string $shiftCode, ?string $pitId = null): array
    {
        $query = MiningHaulCycle::query()
            ->with(['truck', 'shovel', 'histories'])
            ->whereDate('shift_date', $shiftDate)
            ->where('shift_code', $shiftCode)
            ->orderBy('truck_id')
            ->orderBy('cycle_seq');

        if ($pitId !== null) {
            $query->where('pit_id', $pitId);
        }

        $rows = [];
        $truckWait = 0;
        $shovelLastEnd = [];
        $shovelIdle = 0;

        foreach ($query->get() as $cycle) {
            $history = $cycle->histories->sortBy('recorded_at')->values();
            $intervals = [];

            for ($i = 0; $i < $history->count(); $i++) {
                $start = $history[$i]->recorded_at;
                $end = $history[$i + 1]->recorded_at ?? $start;
                $minutes = (int) floor($start->diffInSeconds($end) / 60);
                $stage = $history[$i]->to_status;

                if ($stage === 'queued') {
                    $truckWait += $minutes;
                }

                $intervals[] = [
                    'stage' => $stage,
                    'start' => $start->toIso8601String(),
                    'end' => $end->toIso8601String(),
                    'minutes' => $minutes,
                ];

                if ($stage === 'loading' && $cycle->shovel_id !== null) {
                    if (isset($shovelLastEnd[$cycle->shovel_id]) && $start->greaterThan($shovelLastEnd[$cycle->shovel_id])) {
                        $shovelIdle += (int) floor($shovelLastEnd[$cycle->shovel_id]->diffInSeconds($start) / 60);
                    }
                    $shovelLastEnd[$cycle->shovel_id] = $end;
                }
            }

            $rows[] = [
                'truck' => $cycle->truck?->equipment_code ?? '-',
                'shovel' => $cycle->shovel?->equipment_code ?? '-',
                'cycle_seq' => $cycle->cycle_seq,
                'status' => $cycle->status->value,
                'intervals' => $intervals,
            ];
        }

        return ['rows' => $rows, 'truck_wait_minutes' => $truckWait, 'shovel_idle_minutes' => $shovelIdle];
    }
}
