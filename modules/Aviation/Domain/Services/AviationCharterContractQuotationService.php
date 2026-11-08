<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Services;

class AviationCharterContractQuotationService
{
    /**
     * Hitung kalkulasi harga kontrak sewa carter pesawat (ACMI Wet Lease vs Dry Lease).
     *
     * @param  string  $leaseType  'WET_LEASE' (ACMI) atau 'DRY_LEASE' (Pesawat saja)
     * @param  string  $aircraftType  Tipe pesawat (e.g. 'B738', 'A320', 'A330')
     * @param  int  $guaranteedBlockHours  Jam blok terbang minimum yang dijamin per bulan
     * @param  int  $contractDurationMonths  Durasi kontrak sewa dalam bulan
     * @return array{
     *     lease_type: string,
     *     hourly_rate_idr: int,
     *     monthly_guaranteed_amount_idr: int,
     *     total_contract_value_idr: int,
     *     includes_crew: bool,
     *     includes_maintenance: bool,
     *     includes_insurance: bool
     * }
     */
    public function generateCharterLeaseQuotation(
        string $leaseType,
        string $aircraftType,
        int $guaranteedBlockHours = 100,
        int $contractDurationMonths = 6
    ): array {
        $isWet = strtoupper($leaseType) === 'WET_LEASE';

        // Tarif sewa per jam terbang blok (Block Hour Rate)
        $hourlyRateIdr = match (strtoupper($aircraftType)) {
            'A330', 'B777' => $isWet ? 140000000 : 75000000, // Rp140jt/jam wet, Rp75jt dry
            default => $isWet ? 65000000 : 35000000,         // Rp65jt/jam wet, Rp35jt dry (A320/B738)
        };

        $monthlyGuaranteed = $hourlyRateIdr * $guaranteedBlockHours;
        $totalContract = $monthlyGuaranteed * $contractDurationMonths;

        return [
            'lease_type' => strtoupper($leaseType),
            'hourly_rate_idr' => $hourlyRateIdr,
            'monthly_guaranteed_amount_idr' => $monthlyGuaranteed,
            'total_contract_value_idr' => $totalContract,
            'includes_crew' => $isWet,
            'includes_maintenance' => $isWet,
            'includes_insurance' => $isWet,
        ];
    }
}
