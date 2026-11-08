<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Algorithms;

use Modules\Shared\Domain\Algorithms\Aviation\CodeshareMileageAccrualEngine as SharedEngine;

/**
 * Algoritma A8: Codeshare Mileage Accrual Engine.
 *
 * Mengkalkulasi pembagian pendapatan (revenue proration) antar-maskapai mitra
 * codeshare, menghitung kredit miles frequent flyer berbobot tier, dan memverifikasi
 * keunikan klaim poin untuk mencegah kecurangan klaim ganda (anti-double dipping).
 */
class CodeshareMileageAccrualEngine
{
    private readonly SharedEngine $engine;

    public function __construct(?SharedEngine $engine = null)
    {
        $this->engine = $engine ?? new SharedEngine();
    }

    /**
     * Hitung bagi hasil pendapatan tiket dan akrual poin miles loyalitas.
     *
     * @param  int  $grossFareMinorUnit  Nilai kotor tarif tiket dalam minor unit
     * @param  int  $tpmDistance  Jarak tempuh segmen tiket (Ticketed Point Mileage)
     * @param  string  $cabinClass  Kelas kabin tiket
     * @param  string  $passengerTier  Status tier keanggotaan frequent flyer
     * @param  int  $operatingAirlineBps  Pembagian operating carrier dalam basis poin (e.g. 8500 = 85%)
     * @param  int  $marketingAirlineBps  Pembagian marketing carrier dalam basis poin (e.g. 1500 = 15%)
     * @return array{
     *     gross_fare_minor_unit: int,
     *     operating_share_minor_unit: int,
     *     marketing_share_minor_unit: int,
     *     tpm_distance: int,
     *     cabin_multiplier: float,
     *     tier_bonus_multiplier: float,
     *     base_miles: int,
     *     bonus_miles: int,
     *     miles_accrued: int,
     *     certificate_hash: string
     * }
     */
    public function calculateRevenueShareAndMiles(
        int $grossFareMinorUnit,
        int $tpmDistance,
        string $cabinClass,
        string $passengerTier,
        int $operatingAirlineBps = 8500,
        int $marketingAirlineBps = 1500
    ): array {
        $result = $this->engine->calculate(
            $grossFareMinorUnit,
            $tpmDistance,
            $cabinClass,
            $passengerTier,
            $operatingAirlineBps,
            $marketingAirlineBps
        );

        $cabinFactors = ['first' => 2.0, 'business' => 1.5, 'premium_economy' => 1.25, 'economy_promo' => 0.5];
        $cabinMultiplier = $cabinFactors[strtolower($cabinClass)] ?? 1.0;

        $tierFactors = ['platinum' => 1.0, 'gold' => 0.5, 'silver' => 0.25];
        $tierBonusMultiplier = $tierFactors[strtolower($passengerTier)] ?? 0.0;

        $baseMiles = (int) round($tpmDistance * $cabinMultiplier);
        $bonusMiles = (int) round($baseMiles * $tierBonusMultiplier);

        return [
            'gross_fare_minor_unit' => $result->grossFareMinorUnit,
            'operating_share_minor_unit' => $result->operatingShareMinorUnit,
            'marketing_share_minor_unit' => $result->marketingShareMinorUnit,
            'tpm_distance' => $tpmDistance,
            'cabin_multiplier' => $cabinMultiplier,
            'tier_bonus_multiplier' => $tierBonusMultiplier,
            'base_miles' => $baseMiles,
            'bonus_miles' => $bonusMiles,
            'miles_accrued' => $result->milesAccrued,
            'certificate_hash' => $result->certificate->hash,
        ];
    }

    /**
     * Hasilkan jejak sidik jari unik untuk mendeteksi upaya klaim kredit ganda lintas aliansi.
     */
    public function generateAntiDoubleDipFingerprint(
        string $eTicketNumber,
        string $flightNumber,
        string $departureDateIso
    ): string {
        $normalizedPayload = strtoupper(trim($eTicketNumber)).'|'.
            strtoupper(trim($flightNumber)).'|'.
            trim($departureDateIso);

        return hash('sha256', $normalizedPayload);
    }
}
