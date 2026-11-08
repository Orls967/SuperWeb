<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use InvalidArgumentException;
use Modules\Aviation\Domain\Models\AviationAircraft;
use Modules\Aviation\Domain\Models\AviationAirport;

class AviationCharterQuotationService
{
    /**
     * Hitung estimasi kuotasi carter pesawat privat/eksekutif/kargo ad-hoc terintegrasi.
     *
     * @param array{
     *     aircraft_id: int,
     *     origin_airport_id: int,
     *     destination_airport_id: int,
     *     estimated_flight_hours: float,
     *     passenger_count: int,
     *     vip_catering: bool,
     *     ground_handling_tier: string
     * } $quotationParams
     * @return array{
     *     block_hour_rate_minor: int,
     *     flight_charges_minor: int,
     *     fuel_surcharge_minor: int,
     *     airport_handling_minor: int,
     *     catering_charges_minor: int,
     *     overflight_permits_minor: int,
     *     subtotal_minor: int,
     *     tax_minor: int,
     *     total_quotation_minor: int,
     *     currency: string
     * }
     */
    public function generateQuotation(array $quotationParams): array
    {
        $aircraft = AviationAircraft::findOrFail($quotationParams['aircraft_id']);
        $origin = AviationAirport::findOrFail($quotationParams['origin_airport_id']);
        $destination = AviationAirport::findOrFail($quotationParams['destination_airport_id']);

        $hours = max(1.0, (float) $quotationParams['estimated_flight_hours']);

        // 1. Tarif Sewa per Jam Mesin (Block Hour Rate)
        $hourlyRateMinor = match ($aircraft->icao_type) {
            'B738', 'A320' => 8500000000, // Rp 85.000.000 / jam
            'A333', 'B77W' => 19500000000, // Rp 195.000.000 / jam
            'ATR72' => 4500000000,         // Rp 45.000.000 / jam
            default => 6000000000,
        };

        $flightChargesMinor = (int) round($hours * $hourlyRateMinor);

        // 2. Tambahan Biaya Avtur (Fuel Surcharge) berfluktuasi
        $fuelSurchargeMinor = (int) round($flightChargesMinor * 0.18); // 18% dari flight charges

        // 3. Jasa Penanganan Darat Bandara (Ground Handling & Landing Fee)
        $handlingTierMultiplier = match ($quotationParams['ground_handling_tier'] ?? 'STANDARD') {
            'VIP_FAST_TRACK' => 1.5,
            'HEAD_OF_STATE' => 2.5,
            default => 1.0,
        };

        // Estimasi Rp 15.000.000 per pergerakan bandara x 2 bandara
        $baseAirportHandling = 3000000000;
        $airportHandlingMinor = (int) round($baseAirportHandling * $handlingTierMultiplier);

        // 4. Katering VIP & Kru
        $pax = max(1, (int) $quotationParams['passenger_count']);
        $cateringPerPaxMinor = ($quotationParams['vip_catering'] ?? false) ? 75000000 : 15000000; // Rp 750rb vs Rp 150rb
        $cateringChargesMinor = $pax * $cateringPerPaxMinor;

        // 5. Izin Terbang Ruang Udara (Overflight & Navigation Charges)
        $overflightPermitsMinor = 1200000000; // Rp 12.000.000 pagu navigasi AirNav Indonesia

        // Total Rekapitulasi
        $subtotalMinor = $flightChargesMinor + $fuelSurchargeMinor + $airportHandlingMinor + $cateringChargesMinor + $overflightPermitsMinor;
        $taxMinor = (int) round($subtotalMinor * 0.11); // PPN 11%
        $totalQuotationMinor = $subtotalMinor + $taxMinor;

        return [
            'block_hour_rate_minor' => $hourlyRateMinor,
            'flight_charges_minor' => $flightChargesMinor,
            'fuel_surcharge_minor' => $fuelSurchargeMinor,
            'airport_handling_minor' => $airportHandlingMinor,
            'catering_charges_minor' => $cateringChargesMinor,
            'overflight_permits_minor' => $overflightPermitsMinor,
            'subtotal_minor' => $subtotalMinor,
            'tax_minor' => $taxMinor,
            'total_quotation_minor' => $totalQuotationMinor,
            'currency' => 'IDR',
        ];
    }
}
