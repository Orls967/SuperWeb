<?php

declare(strict_types=1);

namespace Modules\Egy\Application\DTOs;

/**
 * Data Transfer Object untuk Laporan Kinerja Pembangkit Pendingin Distrik (District Cooling).
 */
final class DistrictCoolingPerformanceReportDTO
{
    public function __construct(
        public readonly int $plantId,
        public readonly string $plantName,
        public readonly string $plantCode,
        public readonly int $totalInstalledCapacityRt,
        public readonly int $totalChillersCount,
        public readonly int $activeChillersCount,
        public readonly int $totalCoolingDeliveredRth,
        public readonly int $totalElectricityConsumedKwh,
        public readonly int $averageKwPerTonBps,
        public readonly int $averageCopBps,
        public readonly string $overallStatus,
        public readonly string $verificationHash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'plant_id' => $this->plantId,
            'plant_name' => $this->plantName,
            'plant_code' => $this->plantCode,
            'total_installed_capacity_rt' => $this->totalInstalledCapacityRt,
            'total_chillers_count' => $this->totalChillersCount,
            'active_chillers_count' => $this->activeChillersCount,
            'total_cooling_delivered_rth' => $this->totalCoolingDeliveredRth,
            'total_electricity_consumed_kwh' => $this->totalElectricityConsumedKwh,
            'average_kw_per_ton_bps' => $this->averageKwPerTonBps,
            'average_cop_bps' => $this->averageCopBps,
            'overall_status' => $this->overallStatus,
            'verification_hash' => $this->verificationHash,
        ];
    }
}
