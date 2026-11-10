<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Illuminate\Support\Facades\Log;
use Modules\Core\Application\Services\NotificationService;
use Modules\Core\Domain\Models\SimRun;

/**
 * Pengirim peringatan anomali divergensi dan pelanggaran invarian kernel simulasi.
 */
final class SimulationAnomalyAlertDispatcher
{
    public function __construct(
        private readonly ?NotificationService $notificationService = null,
    ) {}

    public function alertDivergence(string $runA, string $runB, int $day, string $reason): void
    {
        Log::warning("[SIMULATION_DIVERGENCE] Deteksi divergensi antara {$runA} dan {$runB} pada hari {$day}: {$reason}");

        if ($this->notificationService) {
            $this->notificationService->notifyRole(
                'sim_operator',
                'Peringatan Divergensi Simulasi',
                "Sesi {$runA} dan {$runB} menyimpang pada hari ke-{$day}: {$reason}",
                ['type' => 'simulation_divergence', 'run_a' => $runA, 'run_b' => $runB, 'day' => $day]
            );
        }
    }

    public function alertInvariantViolation(SimRun $run, string $invariant, string $message): void
    {
        Log::error("[SIMULATION_INVARIANT_VIOLATION] Sesi {$run->name} melanggar {$invariant}: {$message}");

        if ($this->notificationService) {
            $this->notificationService->notifyRole(
                'admin',
                'Pelanggaran Invarian Kritis Simulasi',
                "Sesi {$run->name} (#{$run->id}) mengalami pelanggaran {$invariant}: {$message}",
                ['type' => 'invariant_violation', 'run_id' => $run->id, 'invariant' => $invariant]
            );
        }
    }
}
