<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

/**
 * Algoritma A8 Shared: Codeshare Mileage Accrual Engine.
 */
class CodeshareMileageAccrualEngine
{
    /**
     * Hitung bagi hasil pendapatan dan akrual miles loyalitas dengan sertifikat integritas.
     */
    public function calculate(
        int $grossFareMinorUnit,
        int $tpmDistance,
        string $cabinClass,
        string $passengerTier,
        int $operatingAirlineBps = 8500,
        int $marketingAirlineBps = 1500
    ): CodeshareMileageResult {
        $totalBps = max(1, $operatingAirlineBps + $marketingAirlineBps);
        $operatingShare = (int) round(($grossFareMinorUnit * $operatingAirlineBps) / (float) $totalBps);
        $marketingShare = $grossFareMinorUnit - $operatingShare;

        $cabinMultiplier = match (strtolower($cabinClass)) {
            'first' => 2.0,
            'business' => 1.5,
            'premium_economy' => 1.25,
            'economy_promo' => 0.5,
            default => 1.0,
        };

        $tierBonusMultiplier = match (strtolower($passengerTier)) {
            'platinum' => 1.0,
            'gold' => 0.5,
            'silver' => 0.25,
            default => 0.0,
        };

        $baseMiles = (int) round($tpmDistance * $cabinMultiplier);
        $bonusMiles = (int) round($baseMiles * $tierBonusMultiplier);
        $totalMilesAccrued = $baseMiles + $bonusMiles;

        $cert = CodeshareMileageCertificate::create(
            $grossFareMinorUnit,
            $operatingShare,
            $marketingShare,
            $totalMilesAccrued
        );

        return new CodeshareMileageResult(
            $grossFareMinorUnit,
            $operatingShare,
            $marketingShare,
            $totalMilesAccrued,
            $cert
        );
    }
}

