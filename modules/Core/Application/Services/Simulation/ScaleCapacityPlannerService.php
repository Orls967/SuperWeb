<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\Models\SimRunTransition;
use Modules\Core\Domain\Models\SimTwinSnapshot;
use Modules\Core\Domain\Models\SimTwinState;

/**
 * Layanan perencana kapasitas basis data dan proyeksi pertumbuhan tabel simulasi.
 */
final class ScaleCapacityPlannerService
{
    /**
     * @return array{
     *     current_row_counts: array<string, int>,
     *     projected_rows_per_year: array<string, int>,
     *     recommendations: array<int, string>
     * }
     */
    public function planCapacity(int $projectedRunsPerMonth = 20, int $avgDaysPerRun = 90): array
    {
        $currentCounts = [
            'sim_runs' => SimRun::count(),
            'sim_checkpoints' => SimCheckpoint::count(),
            'sim_run_transitions' => SimRunTransition::count(),
            'sim_twin_snapshots' => SimTwinSnapshot::count(),
            'sim_twin_states' => SimTwinState::count(),
        ];

        $annualRuns = $projectedRunsPerMonth * 12;
        $checkpointsPerRun = (int) ceil($avgDaysPerRun / 7); // checkpoint mingguan

        $projectedAnnual = [
            'sim_runs' => $annualRuns,
            'sim_checkpoints' => $annualRuns * $checkpointsPerRun,
            'sim_run_transitions' => $annualRuns * 6, // Rata-rata 6 transisi per run
            'sim_twin_snapshots' => $annualRuns * $checkpointsPerRun * 50, // 50 twin entitas
            'sim_twin_states' => $annualRuns * $avgDaysPerRun * 20,
        ];

        $recommendations = [];
        if ($projectedAnnual['sim_twin_states'] > 500000) {
            $recommendations[] = 'Tabel sim_twin_states direkomendasikan dipartisi bulanan berdasarkan created_at.';
        }
        if ($projectedAnnual['sim_checkpoints'] > 50000) {
            $recommendations[] = 'Jalankan sim:checkpoint-prune secara periodik (cron mingguan) untuk membersihkan checkpoint usang.';
        }
        $recommendations[] = 'Aktifkan vacuum / optimize table berkala pada basis data relasional produksi.';

        return [
            'current_row_counts' => $currentCounts,
            'projected_rows_per_year' => $projectedAnnual,
            'recommendations' => $recommendations,
        ];
    }
}
