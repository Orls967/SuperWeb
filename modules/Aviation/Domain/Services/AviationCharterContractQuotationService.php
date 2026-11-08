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

    /**
     * Hitung rincian estimasi biaya penerbangan carter ad-hoc non-terjadwal per rute.
     *
     * @return array{
     *     distance_km: int,
     *     flight_duration_hours: float,
     *     fuel_burn_cost_idr: int,
     *     airport_landing_and_nav_fees_idr: int,
     *     fbo_handling_vip_cost_idr: int,
     *     crew_overnight_allowance_idr: int,
     *     subtotal_direct_operating_cost_idr: int,
     *     commercial_markup_idr: int,
     *     quoted_charter_price_idr: int
     * }
     */
    public function calculateAdHocFlightQuotation(
        int $distanceKm,
        float $mtowTonnes,
        bool $requiresOvernightStay = false,
        float $marginPercentage = 25.0
    ): array {
        $cruisingSpeedKmh = 820.0;
        $flightDurationHours = round(max(0.7, $distanceKm / $cruisingSpeedKmh), 2);

        // Biaya Avtur: rata-rata 2.400 kg/jam x Rp15.000/kg
        $fuelBurnCostIdr = (int) round($flightDurationHours * 2400 * 15000);

        // Biaya Navigasi En-Route & Pendaratan (rumus MTOW x faktor jarak)
        $landingFeeIdr = (int) round($mtowTonnes * 120000);
        $enRouteNavFeeIdr = (int) round($distanceKm * 15000);
        $airportAndNavIdr = $landingFeeIdr + $enRouteNavFeeIdr;

        // Penanganan VIP FBO (Terminal Bisnis Eksekutif)
        $fboHandlingIdr = 25000000;

        // Uang saku & hotel kru jika menginap (Overnight layover)
        $crewAllowanceIdr = $requiresOvernightStay ? 18000000 : 4000000;

        $docTotal = $fuelBurnCostIdr + $airportAndNavIdr + $fboHandlingIdr + $crewAllowanceIdr;
        $markupIdr = (int) round($docTotal * ($marginPercentage / 100.0));
        $quotedPrice = $docTotal + $markupIdr;

        return [
            'distance_km' => $distanceKm,
            'flight_duration_hours' => $flightDurationHours,
            'fuel_burn_cost_idr' => $fuelBurnCostIdr,
            'airport_landing_and_nav_fees_idr' => $airportAndNavIdr,
            'fbo_handling_vip_cost_idr' => $fboHandlingIdr,
            'crew_overnight_allowance_idr' => $crewAllowanceIdr,
            'subtotal_direct_operating_cost_idr' => $docTotal,
            'commercial_markup_idr' => $markupIdr,
            'quoted_charter_price_idr' => $quotedPrice,
        ];
    }
}
