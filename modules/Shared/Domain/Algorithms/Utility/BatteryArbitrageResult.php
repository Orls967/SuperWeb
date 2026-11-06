<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

/**
 * Nilai hasil optimasi jadwal arbitrase baterai (BESS).
 */
final class BatteryArbitrageResult
{
    /**
     * @param  list<array{hour: int, action: string, energy_kwh: int, tariff_idr_per_kwh: int, cost_idr: int, revenue_idr: int, soc_bps: int}>  $hourlySchedule
     */
    public function __construct(
        public readonly int $capacityKwh,
        public readonly int $initialSocBps,
        public readonly int $finalSocBps,
        public readonly int $totalChargedKwh,
        public readonly int $totalDischargedKwh,
        public readonly int $totalChargingCostIdr,
        public readonly int $totalDischargingRevenueIdr,
        public readonly int $totalDegradationCostIdr,
        public readonly int $netArbitrageProfitIdr,
        public readonly array $hourlySchedule,
        public readonly string $certificateHash,
        public readonly string $canonicalPayload,
    ) {}
}
