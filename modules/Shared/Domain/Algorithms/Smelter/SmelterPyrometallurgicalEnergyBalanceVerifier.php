<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Smelter;

final class SmelterPyrometallurgicalEnergyBalanceVerifier
{
    private const MASS_BALANCE_TOLERANCE_PCT = 2.5;

    private const MIN_THERMAL_EFFICIENCY_PCT = 45.0;

    private const MAX_THERMAL_EFFICIENCY_PCT = 95.0;

    public function verify(SmelterPyrometallurgicalEnergyBalanceResult $result, SmelterFeedParameters $feed): bool
    {
        $totalInputMass = $feed->dryOreFeedTons() + $feed->coalReductantTons + $feed->silicaFluxTons + $feed->limestoneFluxTons;
        $totalOutputMass = $result->metalTappedTons + $result->slagTappedTons + $result->offGasDustTons;

        if ($totalInputMass <= 0.0) {
            return false;
        }

        $massDiscrepancyPct = abs($totalInputMass - $totalOutputMass) / $totalInputMass * 100.0;
        if ($massDiscrepancyPct > self::MASS_BALANCE_TOLERANCE_PCT) {
            return false;
        }

        if ($result->thermalEfficiencyPct < self::MIN_THERMAL_EFFICIENCY_PCT || $result->thermalEfficiencyPct > self::MAX_THERMAL_EFFICIENCY_PCT) {
            return false;
        }

        if ($result->nickelRecoveryRatePct <= 0.0 || $result->nickelRecoveryRatePct > 100.0) {
            return false;
        }

        if ($result->metalNiGradePct <= 0.0 || $result->metalNiGradePct > 100.0) {
            return false;
        }

        $expectedHash = hash('sha256', sprintf(
            '%.3f:%.3f:%.3f:%.3f:%.2f',
            $result->metalTappedTons,
            $result->slagTappedTons,
            $result->actualPowerConsumedMwh,
            $result->grossEmissionsTco2e,
            $result->thermalEfficiencyPct
        ));

        return hash_equals($expectedHash, $result->verificationHash);
    }
}
