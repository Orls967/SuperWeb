<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

class AviationInterlineTaxSettlementService
{
    /**
     * Urai komponen tarif kotor tiket menjadi tarif dasar dan komponen pajak wajib regulasi Indonesia.
     *
     * @return array{
     *     base_fare_minor_unit: int,
     *     ppn_11_percent_minor_unit: int,
     *     pjp2u_airport_tax_minor_unit: int,
     *     iwjr_insurance_minor_unit: int,
     *     fuel_surcharge_minor_unit: int,
     *     total_tax_and_fees_minor_unit: int
     * }
     */
    public function decomposeTaxesAndFees(
        int $grossPriceMinorUnit,
        string $departureAirportIata,
        bool $isInternational = false
    ): array {
        // Tarif PJP2U (Pelayanan Jasa Penumpang Pesawat Udara) resmi bandara
        $pjp2u = match (strtoupper($departureAirportIata)) {
            'CGK' => $isInternational ? 25000000 : 13000000, // Rp250.000 intl, Rp130.000 T3 domestik
            'DPS' => $isInternational ? 24000000 : 12000000,
            'SUB' => $isInternational ? 23000000 : 11000000,
            'KNO' => $isInternational ? 22000000 : 10000000,
            default => $isInternational ? 20000000 : 8500000, // Bandara regional
        };

        // Iuran Wajib Jasa Raharja (IWJR) tetap: Rp5.000 per tiket penerbangan domestik
        $iwjr = $isInternational ? 0 : 500000;

        // Fuel Surcharge (Tarif Tuslah Bahan Bakar) rata-rata 10% dari tarif dasar
        $taxableBase = max(0, $grossPriceMinorUnit - $pjp2u - $iwjr);

        // Rumus PPN 11% inklusif: Base = Taxable / 1.11; PPN = Taxable - Base
        $baseFareWithSurcharge = (int) round($taxableBase / 1.11);
        $ppn11 = $taxableBase - $baseFareWithSurcharge;

        $fuelSurcharge = (int) round($baseFareWithSurcharge * 0.10);
        $pureBaseFare = max(0, $baseFareWithSurcharge - $fuelSurcharge);

        $totalTaxes = $ppn11 + $pjp2u + $iwjr + $fuelSurcharge;

        return [
            'base_fare_minor_unit' => $pureBaseFare,
            'ppn_11_percent_minor_unit' => $ppn11,
            'pjp2u_airport_tax_minor_unit' => $pjp2u,
            'iwjr_insurance_minor_unit' => $iwjr,
            'fuel_surcharge_minor_unit' => $fuelSurcharge,
            'total_tax_and_fees_minor_unit' => $totalTaxes,
        ];
    }
}
