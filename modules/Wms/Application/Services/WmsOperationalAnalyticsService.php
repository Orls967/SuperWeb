<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\Models\DockAppointment;
use Modules\Wms\Domain\Models\Slotting;
use Modules\Wms\Domain\Models\Task;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\Models\Wave;
use Modules\Wms\Domain\Services\DockSchedulingOptimizationService;
use Modules\Wms\Domain\Services\WavePickPathOptimizationService;

/**
 * Layanan analitik operasional gudang terpadu (satuan permil integer 0..1000)
 * serta pembangun data & ekspor CSV untuk picking waves dan dock appointments.
 */
final class WmsOperationalAnalyticsService
{
    public const DEFAULT_DOORS_PER_WAREHOUSE = 4;

    public const DEFAULT_SHIFT_MINUTES_PER_DAY = 480;

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly WavePickPathOptimizationService $pickPathOptimizer,
        private readonly DockSchedulingOptimizationService $dockOptimizer,
    ) {}

    /**
     * Menyusun ringkasan metrik operasional gudang terpadu dalam satuan permil (0..1000).
     *
     * @return array{
     *     picking_wave_completion_permil: int,
     *     dock_utilization_permil: int,
     *     slotting_abc_accuracy_permil: int,
     *     quarantine_release_rate_permil: int,
     *     total_waves: int,
     *     released_waves: int,
     *     total_pick_tasks: int,
     *     completed_pick_tasks: int,
     *     open_pick_tasks: int,
     *     active_dock_appointments: int,
     *     scheduled_dock_minutes: int,
     *     slotting_evaluated_count: int,
     *     slotting_accurate_count: int,
     *     quarantined_total: int,
     *     quarantined_released: int,
     *     snapshot_at: string
     * }
     */
    public function summarize(
        int $doorsPerWarehouse = self::DEFAULT_DOORS_PER_WAREHOUSE,
        int $shiftMinutesPerDay = self::DEFAULT_SHIFT_MINUTES_PER_DAY,
    ): array {
        $pickingMetrics = $this->computePickingWaveMetrics();
        $dockMetrics = $this->computeDockUtilizationMetrics($doorsPerWarehouse, $shiftMinutesPerDay);
        $slottingMetrics = $this->computeSlottingAccuracyMetrics();
        $quarantineMetrics = $this->computeQuarantineReleaseMetrics();

        return [
            'picking_wave_completion_permil' => $pickingMetrics['completion_permil'],
            'dock_utilization_permil' => $dockMetrics['utilization_permil'],
            'slotting_abc_accuracy_permil' => $slottingMetrics['accuracy_permil'],
            'quarantine_release_rate_permil' => $quarantineMetrics['release_rate_permil'],
            'total_waves' => $pickingMetrics['total_waves'],
            'released_waves' => $pickingMetrics['released_waves'],
            'total_pick_tasks' => $pickingMetrics['total_pick_tasks'],
            'completed_pick_tasks' => $pickingMetrics['completed_pick_tasks'],
            'open_pick_tasks' => $pickingMetrics['open_pick_tasks'],
            'active_dock_appointments' => $dockMetrics['active_appointments'],
            'scheduled_dock_minutes' => $dockMetrics['scheduled_minutes'],
            'slotting_evaluated_count' => $slottingMetrics['evaluated_count'],
            'slotting_accurate_count' => $slottingMetrics['accurate_count'],
            'quarantined_total' => $quarantineMetrics['total_quarantined'],
            'quarantined_released' => $quarantineMetrics['released_count'],
            'snapshot_at' => $this->clock->now()->toIso8601String(),
        ];
    }

    /**
     * Membangun baris terstruktur untuk tabel & dashboard picking waves.
     *
     * @return list<array{
     *     wave_code: string,
     *     strategy: string,
     *     status: string,
     *     task_count: int,
     *     completed_count: int,
     *     total_qty: int,
     *     estimated_seconds: int,
     *     released_at: string
     * }>
     */
    public function pickingTableRows(int $limit = 20): array
    {
        $waves = Wave::query()
            ->with(['tasks.bin.rack.zone'])
            ->latest('id')
            ->limit($limit)
            ->get();

        $rows = [];
        foreach ($waves as $wave) {
            $tasks = $wave->tasks;
            $completed = $tasks->filter(
                static fn (Task $t): bool => in_array((string) $t->status, ['done', 'closed'], true)
            )->count();
            $totalQty = (int) $tasks->sum(static fn (Task $t): int => (int) round((float) $t->qty));
            $optimization = $tasks->isNotEmpty()
                ? $this->pickPathOptimizer->optimizeFromEloquentTasks($tasks)
                : ['estimated_total_seconds' => 0];

            $rows[] = [
                'wave_code' => (string) $wave->code,
                'strategy' => strtoupper((string) $wave->strategy),
                'status' => (string) $wave->status,
                'task_count' => $tasks->count(),
                'completed_count' => $completed,
                'total_qty' => $totalQty,
                'estimated_seconds' => (int) ($optimization['estimated_total_seconds'] ?? 0),
                'released_at' => $wave->released_at instanceof CarbonInterface
                    ? $wave->released_at->format('Y-m-d H:i')
                    : '-',
            ];
        }

        return $rows;
    }

    /**
     * Membangun baris terstruktur untuk tabel & dashboard dock appointments.
     *
     * @return list<array{
     *     dock_door: string,
     *     warehouse_code: string,
     *     reference: string,
     *     direction: string,
     *     window: string,
     *     status: string,
     *     sla_score_bps: int,
     *     cutoff_risk: string
     * }>
     */
    public function dockTableRows(int $limit = 20): array
    {
        $docks = DockAppointment::query()
            ->with('warehouse')
            ->latest('id')
            ->limit($limit)
            ->get();

        $evaluation = $this->dockOptimizer->evaluateFromEloquentAppointments($docks);
        $assessmentById = [];
        foreach ($evaluation['assessments'] as $item) {
            $assessmentById[(string) $item['appointment_id']] = $item;
        }

        $rows = [];
        foreach ($docks as $idx => $dock) {
            $assessment = $assessmentById[(string) $dock->id] ?? null;
            $doors = DockSchedulingOptimizationService::DEFAULT_DOORS;
            $dockDoor = (string) ($assessment['dock_door'] ?? $dock->getAttribute('dock_door') ?: $doors[$idx % count($doors)]);

            $startLabel = $dock->window_start instanceof CarbonInterface ? $dock->window_start->format('d M H:i') : '-';
            $endLabel = $dock->window_end instanceof CarbonInterface ? $dock->window_end->format('H:i') : '-';

            $rows[] = [
                'dock_door' => $dockDoor,
                'warehouse_code' => (string) ($dock->warehouse?->code ?? $dock->warehouse_id),
                'reference' => (string) $dock->reference,
                'direction' => (string) ($assessment['direction'] ?? $this->dockOptimizer->normalizeDirection((string) $dock->direction)),
                'window' => "{$startLabel} - {$endLabel}",
                'status' => (string) $dock->status,
                'sla_score_bps' => (int) ($assessment['sla_score_bps'] ?? 10000),
                'cutoff_risk' => (string) ($assessment['cutoff_risk'] ?? 'LOW'),
            ];
        }

        return $rows;
    }

    /**
     * Membangun konten CSV deterministik untuk seluruh wave & tugas picking.
     */
    public function buildPickingCsv(int $limit = 500): string
    {
        $tasks = Task::query()
            ->where('kind', 'pick')
            ->with(['bin.rack.zone'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $wavesById = Wave::query()->get()->keyBy('id');

        $rows = [[
            'wave_id',
            'wave_code',
            'strategy',
            'wave_status',
            'released_at',
            'task_id',
            'product_id',
            'qty',
            'bin_code',
            'zone_code',
            'task_status',
        ]];

        foreach ($tasks as $task) {
            /** @var Wave|null $wave */
            $wave = $task->wave_id !== null ? $wavesById->get($task->wave_id) : null;
            $rows[] = [
                $task->wave_id ?? '',
                $this->sanitizeCsvCell((string) ($wave?->code ?? '')),
                $this->sanitizeCsvCell((string) ($wave?->strategy ?? '')),
                $this->sanitizeCsvCell((string) ($wave?->status ?? '')),
                $wave?->released_at instanceof CarbonInterface ? $wave->released_at->toIso8601String() : '',
                $task->id,
                $task->product_id,
                (int) round((float) $task->qty),
                $this->sanitizeCsvCell((string) ($task->bin?->code ?? '')),
                $this->sanitizeCsvCell((string) ($task->bin?->rack?->zone?->code ?? '')),
                $this->sanitizeCsvCell((string) $task->status),
            ];
        }

        return $this->encodeCsv($rows);
    }

    /**
     * Membangun konten CSV deterministik untuk jadwal dermaga & skor SLA/cutoff.
     */
    public function buildDocksCsv(int $limit = 500): string
    {
        $docks = DockAppointment::query()
            ->with('warehouse')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $evaluation = $this->dockOptimizer->evaluateFromEloquentAppointments($docks);
        $assessmentById = [];
        foreach ($evaluation['assessments'] as $item) {
            $assessmentById[(string) $item['appointment_id']] = $item;
        }

        $rows = [[
            'appointment_id',
            'warehouse_code',
            'dock_door',
            'direction',
            'reference',
            'carrier',
            'window_start',
            'window_end',
            'status',
            'sla_score_bps',
            'cutoff_risk',
        ]];

        $doors = DockSchedulingOptimizationService::DEFAULT_DOORS;
        foreach ($docks as $idx => $dock) {
            $assessment = $assessmentById[(string) $dock->id] ?? null;
            $dockDoor = (string) ($assessment['dock_door'] ?? $dock->getAttribute('dock_door') ?: $doors[$idx % count($doors)]);

            $rows[] = [
                $dock->id,
                $this->sanitizeCsvCell((string) ($dock->warehouse?->code ?? $dock->warehouse_id)),
                $this->sanitizeCsvCell($dockDoor),
                $this->sanitizeCsvCell((string) ($assessment['direction'] ?? $dock->direction)),
                $this->sanitizeCsvCell((string) $dock->reference),
                $this->sanitizeCsvCell((string) ($dock->carrier ?? '')),
                $dock->window_start instanceof CarbonInterface ? $dock->window_start->toIso8601String() : '',
                $dock->window_end instanceof CarbonInterface ? $dock->window_end->toIso8601String() : '',
                $this->sanitizeCsvCell((string) $dock->status),
                (int) ($assessment['sla_score_bps'] ?? 10000),
                $this->sanitizeCsvCell((string) ($assessment['cutoff_risk'] ?? 'LOW')),
            ];
        }

        return $this->encodeCsv($rows);
    }

    /**
     * @return array{
     *     completion_permil: int,
     *     total_waves: int,
     *     released_waves: int,
     *     total_pick_tasks: int,
     *     completed_pick_tasks: int,
     *     open_pick_tasks: int
     * }
     */
    private function computePickingWaveMetrics(): array
    {
        $totalWaves = Wave::query()->count();
        $releasedWaves = Wave::query()->whereIn('status', ['released', 'closed'])->count();

        $pickTasks = Task::query()
            ->where('kind', 'pick')
            ->where('status', '!=', 'cancelled')
            ->get(['id', 'status']);

        $totalPickTasks = $pickTasks->count();
        $completedPickTasks = $pickTasks->filter(
            static fn (Task $t): bool => in_array((string) $t->status, ['done', 'closed'], true)
        )->count();
        $openPickTasks = $totalPickTasks - $completedPickTasks;

        if ($totalPickTasks > 0) {
            $completionPermil = intdiv($completedPickTasks * 1000, $totalPickTasks);
        } elseif ($totalWaves > 0) {
            $completionPermil = intdiv($releasedWaves * 1000, $totalWaves);
        } else {
            $completionPermil = 0;
        }

        return [
            'completion_permil' => $completionPermil,
            'total_waves' => $totalWaves,
            'released_waves' => $releasedWaves,
            'total_pick_tasks' => $totalPickTasks,
            'completed_pick_tasks' => $completedPickTasks,
            'open_pick_tasks' => $openPickTasks,
        ];
    }

    /**
     * @return array{utilization_permil: int, active_appointments: int, scheduled_minutes: int}
     */
    private function computeDockUtilizationMetrics(int $doorsPerWarehouse, int $shiftMinutesPerDay): array
    {
        $activeAppointments = DockAppointment::query()
            ->where('status', '!=', 'cancelled')
            ->get(['id', 'window_start', 'window_end']);

        $scheduledMinutes = 0;
        foreach ($activeAppointments as $dock) {
            if ($dock->window_start instanceof CarbonInterface && $dock->window_end instanceof CarbonInterface) {
                $scheduledMinutes += max(0, (int) $dock->window_start->diffInMinutes($dock->window_end, false));
            }
        }

        $activeWarehouses = max(1, Warehouse::query()->where('is_active', true)->count());
        $totalCapacityMinutes = max(1, $activeWarehouses * max(1, $doorsPerWarehouse) * max(1, $shiftMinutesPerDay));
        $utilizationPermil = min(1000, intdiv($scheduledMinutes * 1000, $totalCapacityMinutes));

        return [
            'utilization_permil' => $utilizationPermil,
            'active_appointments' => $activeAppointments->count(),
            'scheduled_minutes' => $scheduledMinutes,
        ];
    }

    /**
     * @return array{accuracy_permil: int, evaluated_count: int, accurate_count: int}
     */
    private function computeSlottingAccuracyMetrics(): array
    {
        $slottings = Slotting::query()->get();
        $evaluatedCount = $slottings->count();

        if ($evaluatedCount === 0) {
            return [
                'accuracy_permil' => 0,
                'evaluated_count' => 0,
                'accurate_count' => 0,
            ];
        }

        $stockLocations = DB::table('wms_bin_stocks as bs')
            ->join('wms_bins as b', 'b.id', '=', 'bs.bin_id')
            ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
            ->join('wms_zones as z', 'z.id', '=', 'r.zone_id')
            ->where('bs.qty', '>', 0)
            ->select(['bs.product_id', 'b.is_pick_face', 'z.id as zone_id', 'z.kind as zone_kind'])
            ->get()
            ->groupBy('product_id');

        $accurateCount = 0;
        foreach ($slottings as $slotting) {
            $productStocks = $stockLocations->get($slotting->product_id);
            if ($productStocks === null || $productStocks->isEmpty()) {
                $accurateCount++;

                continue;
            }

            $abc = strtoupper((string) $slotting->abc_class);
            $matched = $productStocks->contains(function (object $loc) use ($slotting, $abc): bool {
                if ($slotting->suggested_zone_id !== null && (int) $loc->zone_id === (int) $slotting->suggested_zone_id) {
                    return true;
                }
                if ($abc === 'A') {
                    return (bool) $loc->is_pick_face || $loc->zone_kind === 'pick';
                }
                if ($abc === 'C') {
                    return ! (bool) $loc->is_pick_face;
                }

                return true;
            });

            if ($matched) {
                $accurateCount++;
            }
        }

        return [
            'accuracy_permil' => intdiv($accurateCount * 1000, $evaluatedCount),
            'evaluated_count' => $evaluatedCount,
            'accurate_count' => $accurateCount,
        ];
    }

    /**
     * @return array{release_rate_permil: int, total_quarantined: int, released_count: int}
     */
    private function computeQuarantineReleaseMetrics(): array
    {
        $rows = DB::table('wms_bin_stocks')
            ->where(function ($query): void {
                $query->where('status', 'quarantine')
                    ->orWhere('stock_status', 'quarantine')
                    ->orWhereNotNull('quarantined_at')
                    ->orWhereNotNull('released_at');
            })
            ->get(['status', 'stock_status', 'released_at']);

        $totalQuarantined = $rows->count();
        if ($totalQuarantined === 0) {
            return [
                'release_rate_permil' => 0,
                'total_quarantined' => 0,
                'released_count' => 0,
            ];
        }

        $releasedCount = $rows->filter(
            static fn (object $row): bool => $row->released_at !== null
                && ($row->status === 'available' || $row->stock_status === 'available')
        )->count();

        return [
            'release_rate_permil' => intdiv($releasedCount * 1000, $totalQuarantined),
            'total_quarantined' => $totalQuarantined,
            'released_count' => $releasedCount,
        ];
    }

    private function sanitizeCsvCell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  list<list<int|string>>  $rows
     */
    private function encodeCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
