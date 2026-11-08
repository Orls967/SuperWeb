<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Algorithms;

/**
 * Algoritma A8: Codeshare Mileage Accrual Engine.
 *
 * Mengkalkulasi pembagian pendapatan (revenue proration) antar-maskapai mitra
 * codeshare, menghitung kredit miles frequent flyer berbobot tier, dan memverifikasi
 * keunikan klaim poin untuk mencegah kecurangan klaim ganda (anti-double dipping).
 */
class CodeshareMileageAccrualEngine
{
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
        // Validasi proporsi basis poin total 10.000 (100%)
        $totalBps = $operatingAirlineBps + $marketingAirlineBps;
        if ($totalBps <= 0) {
            $operatingAirlineBps = 8500;
            $marketingAirlineBps = 1500;
            $totalBps = 10000;
        }

        $operatingShare = (int) round(($grossFareMinorUnit * $operatingAirlineBps) / (float) $totalBps);
        $marketingShare = $grossFareMinorUnit - $operatingShare;

        // Faktor pengali kelas kabin
        $cabinMultiplier = match (strtolower($cabinClass)) {
            'first' => 2.0,
            'business' => 1.5,
            'premium_economy' => 1.25,
            'economy_promo' => 0.5,
            default => 1.0, // economy_standard
        };

        // Faktor pengali bonus tier loyalitas
        $tierBonusMultiplier = match (strtolower($passengerTier)) {
            'platinum' => 1.0,  // +100% bonus
            'gold' => 0.5,      // +50% bonus
            'silver' => 0.25,   // +25% bonus
            default => 0.0,     // classic / blue tanpa bonus
        };

        $baseMiles = (int) round($tpmDistance * $cabinMultiplier);
        $bonusMiles = (int) round($baseMiles * $tierBonusMultiplier);
        $totalMilesAccrued = $baseMiles + $bonusMiles;

        $certHash = hash('sha256', sprintf(
            '%d:%d:%s:%s:%d:%d:%d',
            $grossFareMinorUnit,
            $tpmDistance,
            $cabinClass,
            $passengerTier,
            $operatingShare,
            $marketingShare,
            $totalMilesAccrued
        ));

        return [
            'gross_fare_minor_unit' => $grossFareMinorUnit,
            'operating_share_minor_unit' => $operatingShare,
            'marketing_share_minor_unit' => $marketingShare,
            'tpm_distance' => $tpmDistance,
            'cabin_multiplier' => $cabinMultiplier,
            'tier_bonus_multiplier' => $tierBonusMultiplier,
            'base_miles' => $baseMiles,
            'bonus_miles' => $bonusMiles,
            'miles_accrued' => $totalMilesAccrued,
            'certificate_hash' => $certHash,
        ];
    }

    /**
     * Bangkitkan token hash deterministik anti-double dip untuk klaim poin miles.
     */
    public function generateClaimNonceHash(string $ticketNumber, int $couponNumber, string $flightDate): string
    {
        $normalizedTicket = trim($ticketNumber);
        $payload = sprintf('%s|CPN:%d|DATE:%s|SALT:AVI-LOYALTY-G7-P7', $normalizedTicket, $couponNumber, $flightDate);

        return hash('sha256', $payload);
    }
}
