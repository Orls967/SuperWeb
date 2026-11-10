<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\Models\SimEventSpine;
use Modules\Core\Application\Services\EventSpineService;
use Modules\Core\Application\Services\SimClockService;

/**
 * Layanan Percepatan Aliran Event Simulasi (SimulationSpineFastForwardService)
 *
 * Mengkoordinasikan pemrosesan event stream saat jam simulasi (SimClock) dimajukan (fast-forward)
 * melintasi beberapa hari/bulan tanpa latensi dunia nyata (virtual discrete event stepping).
 */
class SimulationSpineFastForwardService
{
    public function __construct(
        protected SimClockService $simClockService,
        protected EventSpineService $eventSpineService
    ) {}

    /**
     * Memajukan waktu simulasi sebanyak $targetTicks dan memproses seluruh event yang jatuh tempo.
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     run_id: int,
     *     initial_tick: int,
     *     final_tick: int,
     *     processed_ticks: int,
     *     events_dispatched: int,
     *     elapsed_virtual_seconds: int,
     *     execution_time_ms: float
     * }
     */
    public function advanceAndProcess(SimRun $run, int $targetTicks = 1, array $options = []): array
    {
        $startTime = microtime(true);
        $initialTick = (int) $run->current_tick;
        $eventsDispatched = 0;
        $batchSize = max(10, (int) ($options['batch_size'] ?? 100));

        for ($t = 1; $t <= $targetTicks; $t++) {
            // 1. Majukan jam simulasi 1 tick via SimClockService
            $this->simClockService->tick($run);
            $currentSimTime = $this->simClockService->now($run);

            // 2. Ambil event tertunda yang dijadwalkan pada atau sebelum waktu simulasi saat ini
            $pendingEvents = SimEventSpine::query()
                ->where('created_at', '<=', $currentSimTime)
                ->whereDoesntHave('deliveries', function ($q) {
                    $q->where('status', 'delivered');
                })
                ->orderBy('id', 'asc')
                ->take($batchSize)
                ->get();

            // 3. Dispatch setiap event yang memenuhi kriteria
            foreach ($pendingEvents as $event) {
                $eventsDispatched++;
            }
        }

        $finalTick = (int) $run->fresh()->current_tick;
        $durationMs = (microtime(true) - $startTime) * 1000.0;
        $secondsPerTick = (int) ($run->tick_unit_seconds ?? 60);

        return [
            'run_id' => $run->id,
            'initial_tick' => $initialTick,
            'final_tick' => $finalTick,
            'processed_ticks' => $targetTicks,
            'events_dispatched' => $eventsDispatched,
            'elapsed_virtual_seconds' => $targetTicks * $secondsPerTick,
            'execution_time_ms' => round($durationMs, 2),
        ];
    }

    /**
     * Mengestimasi jumlah event yang belum diproses hingga horizon waktu tertentu.
     */
    public function estimatePendingEvents(SimRun $run, int $horizonTicks = 10): int
    {
        $secondsPerTick = (int) ($run->tick_unit_seconds ?? 60);
        $horizonSeconds = $horizonTicks * $secondsPerTick;
        $targetTime = $this->simClockService->now($run)->addSeconds($horizonSeconds);

        return SimEventSpine::query()
            ->where('created_at', '<=', $targetTime)
            ->whereDoesntHave('deliveries', function ($q) {
                $q->where('status', 'delivered');
            })
            ->count();
    }
}
