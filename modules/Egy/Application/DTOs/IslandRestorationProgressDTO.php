<?php

declare(strict_types=1);

namespace Modules\Egy\Application\DTOs;

/**
 * DTO Pemantauan Progres Pemulihan Bertahap Microgrid (Staggered Load Re-energization).
 */
final class IslandRestorationProgressDTO
{
    /**
     * @param  list<array<string, mixed>>  $restorationStepsLog
     */
    public function __construct(
        public readonly string $microgridCode,
        public readonly string $restorationPhase, // STABILIZING, SOFT_LOADING, SYNCHRONIZING, FULLY_RESTORED
        public readonly int $currentFrequencyMhz,
        public readonly int $currentVoltageVolt,
        public readonly int $totalFeedersCount,
        public readonly int $energizedFeedersCount,
        public readonly int $servedLoadKw,
        public readonly int $spinningReserveHeadroomKw,
        public readonly bool $isReadyForGridReconnection,
        public readonly array $restorationStepsLog,
        public readonly string $checksumSha256,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'microgrid_code' => $this->microgridCode,
            'restoration_phase' => $this->restorationPhase,
            'frequency_mhz' => $this->currentFrequencyMhz,
            'voltage_volt' => $this->currentVoltageVolt,
            'total_feeders' => $this->totalFeedersCount,
            'energized_feeders' => $this->energizedFeedersCount,
            'served_load_kw' => $this->servedLoadKw,
            'spinning_reserve_headroom_kw' => $this->spinningReserveHeadroomKw,
            'ready_for_grid_reconnection' => $this->isReadyForGridReconnection,
            'steps_log' => $this->restorationStepsLog,
            'checksum_sha256' => $this->checksumSha256,
        ];
    }
}
