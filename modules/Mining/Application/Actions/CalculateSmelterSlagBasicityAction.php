<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;

final class CalculateSmelterSlagBasicityAction
{
    /**
     * Menghitung rasio basicity terak smelter (MgO + CaO) / SiO2
     * untuk menjaga kestabilan viskositas terak dan integritas refraktori tanur RKEF.
     *
     * @return array{
     *     sio2_pct: float,
     *     mgo_pct: float,
     *     cao_pct: float,
     *     basicity_ratio: float,
     *     refractory_risk_level: string,
     *     recommended_silica_flux_adj_tons: float
     * }
     */
    public function execute(float $sio2Pct, float $mgoPct, float $caoPct = 2.5): array
    {
        if ($sio2Pct <= 0.0) {
            throw new InvalidArgumentException('Kadar SiO2 harus lebih besar dari 0%.');
        }

        $basicity = round(($mgoPct + $caoPct) / $sio2Pct, 3);

        // Optimal basicity range for RKEF NPI: 0.85 - 1.15
        if ($basicity < 0.80) {
            $risk = 'ACIDIC_SLAG_HIGH_REFRACTORY_CORROSION';
            $silicaAdj = -2.5; // kurangi flux silika
        } elseif ($basicity > 1.25) {
            $risk = 'BASIC_SLAG_HIGH_VISCOSITY_FOAMING';
            $silicaAdj = 3.0; // tambah flux silika
        } else {
            $risk = 'OPTIMAL_NEUTRAL_VISCOSITY';
            $silicaAdj = 0.0;
        }

        return [
            'sio2_pct' => $sio2Pct,
            'mgo_pct' => $mgoPct,
            'cao_pct' => $caoPct,
            'basicity_ratio' => $basicity,
            'refractory_risk_level' => $risk,
            'recommended_silica_flux_adj_tons' => $silicaAdj,
        ];
    }
}
