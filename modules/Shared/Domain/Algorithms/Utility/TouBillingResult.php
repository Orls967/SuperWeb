<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

/**
 * Result data object for E1 Utility\TouBilling.
 */
final class TouBillingResult
{
    /**
     * @param  list<array{window: string, kwh: int, rate_idr_per_kwh: int, charge_idr: int, reading_count: int}>  $windowCharges
     * @param  array{
     *     total_reading_kwh: int,
     *     sum_window_kwh: int,
     *     energy_charge_idr: int,
     *     peak_kw: int,
     *     demand_tariff_idr_per_kw: int,
     *     demand_charge_idr: int,
     *     total_charge_idr: int,
     *     reading_count: int,
     *     digest: string
     * }  $certificate
     */
    public function __construct(
        public readonly array $windowCharges,
        public readonly int $energyChargeIdr,
        public readonly int $peakKw,
        public readonly int $demandTariffIdrPerKw,
        public readonly int $demandChargeIdr,
        public readonly int $totalChargeIdr,
        public readonly array $certificate
    ) {}
}
