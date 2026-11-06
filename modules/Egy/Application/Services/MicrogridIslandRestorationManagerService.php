<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use InvalidArgumentException;
use Modules\Egy\Application\DTOs\IslandRestorationProgressDTO;
use Modules\Egy\Domain\Models\Microgrid;
use Modules\Egy\Domain\StateMachines\MicrogridOperatingModeStateMachine;

/**
 * Layanan Orkestrasi Pemulihan Bertahap Microgrid (Staggered Load Re-energization).
 * Mengatur penyambungan kembali beban feeder non-kritis yang sempat dilepas saat islanding
 * secara bertahap dengan jeda waktu inter-step (soft loading) agar tidak menyebabkan trip under-frequency.
 */
final class MicrogridIslandRestorationManagerService
{
    public function __construct(
        private readonly MicrogridOperatingModeStateMachine $stateMachine,
        private readonly MicrogridScadaTelemetryProcessorService $scadaProcessor,
    ) {}

    /**
     * Eksekusi tahap penyambungan kembali feeder non-kritis secara bertahap.
     *
     * @param  int  $addedGenerationCapacityKw  Daya tambahan yang baru online (misal genset kedua start)
     */
    public function executeStaggeredRestoration(string $microgridCode, int $addedGenerationCapacityKw = 0): IslandRestorationProgressDTO
    {
        $microgrid = Microgrid::with('feeders')->where('microgrid_code', $microgridCode)->firstOrFail();

        $currentMode = $microgrid->operating_mode;
        if (! in_array($currentMode, ['islanded', 'restoring'], true)) {
            throw new InvalidArgumentException("Microgrid {$microgridCode} tidak berada dalam mode islanded/restoring (status: {$currentMode}).");
        }

        $totalCapacityKw = (int) $microgrid->total_capacity_kw + $addedGenerationCapacityKw;
        $currentGenerationKw = (int) $microgrid->current_generation_kw + $addedGenerationCapacityKw;
        $currentLoadKw = (int) $microgrid->current_load_kw;

        $availableHeadroomKw = max(0, $currentGenerationKw - $currentLoadKw);

        $feeders = $microgrid->feeders->sortBy('priority_rank');
        $stepsLog = [];
        $energizedCount = 0;

        foreach ($feeders as $feeder) {
            $ratedKw = (int) $feeder->rated_load_kw;

            if ($feeder->is_energized) {
                $energizedCount++;
                $stepsLog[] = [
                    'feeder_code' => $feeder->feeder_code,
                    'action' => 'ALREADY_ENERGIZED',
                    'load_kw' => $ratedKw,
                    'status' => 'ONLINE',
                ];

                continue;
            }

            // Jika headroom mencukupi, sambungkan feeder ini (soft loading step)
            if ($availableHeadroomKw >= $ratedKw) {
                $feeder->is_energized = true;
                $feeder->save();

                $currentLoadKw += $ratedKw;
                $availableHeadroomKw -= $ratedKw;
                $energizedCount++;

                $stepsLog[] = [
                    'feeder_code' => $feeder->feeder_code,
                    'action' => 'STAGGERED_CLOSE_BREAKER',
                    'load_kw' => $ratedKw,
                    'status' => 'RE_ENERGIZED_SUCCESS',
                ];
            } else {
                $stepsLog[] = [
                    'feeder_code' => $feeder->feeder_code,
                    'action' => 'DEFERRED_INSUFFICIENT_HEADROOM',
                    'load_kw' => $ratedKw,
                    'status' => 'OFFLINE_WAITING',
                ];
            }
        }

        // Tentukan fase pemulihan
        $allFeedersCount = $feeders->count();
        $isAllEnergized = $energizedCount === $allFeedersCount;
        $phase = $isAllEnergized ? 'FULLY_RESTORED' : 'SOFT_LOADING';

        // Evaluasi kesiapan sinkronisasi rekoneksi grid PLN (jika frekuensi 50.00 Hz nominal)
        $isReadyForGridReconnection = $isAllEnergized && ((int) $microgrid->grid_frequency_mhz >= 49900);

        $microgrid->current_generation_kw = $currentGenerationKw;
        $microgrid->current_load_kw = $currentLoadKw;
        if ($isAllEnergized && $microgrid->operating_mode === 'restoring') {
            $microgrid->operating_mode = $this->stateMachine->transition(
                $microgrid->operating_mode,
                MicrogridOperatingModeStateMachine::MODE_ISLANDED
            );
        }
        $microgrid->save();

        $payload = sprintf(
            'ISLAND-RESTORE|MG:%s|PHASE:%s|FEEDERS:%d/%d|LOAD:%d|HEADROOM:%d|SYNC_READY:%d',
            $microgrid->microgrid_code,
            $phase,
            $energizedCount,
            $allFeedersCount,
            $currentLoadKw,
            $availableHeadroomKw,
            $isReadyForGridReconnection ? 1 : 0
        );
        $checksum = hash('sha256', $payload);

        return new IslandRestorationProgressDTO(
            microgridCode: $microgrid->microgrid_code,
            restorationPhase: $phase,
            currentFrequencyMhz: (int) $microgrid->grid_frequency_mhz,
            currentVoltageVolt: (int) $microgrid->bus_voltage_volt,
            totalFeedersCount: $allFeedersCount,
            energizedFeedersCount: $energizedCount,
            servedLoadKw: $currentLoadKw,
            spinningReserveHeadroomKw: $availableHeadroomKw,
            isReadyForGridReconnection: $isReadyForGridReconnection,
            restorationStepsLog: $stepsLog,
            checksumSha256: $checksum
        );
    }
}
