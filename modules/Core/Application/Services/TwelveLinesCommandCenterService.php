<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Banking\Contracts\Ledger;

class TwelveLinesCommandCenterService
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Compute Group-wide 12-Lines ESG Carbon Balance
     * Scope 1 + Scope 2 + Scope 3 aggregation
     */
    public function aggregateTwelveLinesCarbonEmissions(array $emissionsByLine): array
    {
        $totalScope1Kg = 0.00;
        $totalScope2Kg = 0.00;
        $totalScope3Kg = 0.00;

        foreach ($emissionsByLine as $line => $data) {
            $totalScope1Kg += (float) ($data['scope_1_kg'] ?? 0);
            $totalScope2Kg += (float) ($data['scope_2_kg'] ?? 0);
            $totalScope3Kg += (float) ($data['scope_3_kg'] ?? 0);
        }

        $totalEmissionsKg = $totalScope1Kg + $totalScope2Kg + $totalScope3Kg;
        $totalTonCo2e = round($totalEmissionsKg / 1000.0, 3);

        return [
            'total_scope_1_kg' => round($totalScope1Kg, 2),
            'total_scope_2_kg' => round($totalScope2Kg, 2),
            'total_scope_3_kg' => round($totalScope3Kg, 2),
            'total_emissions_kg' => round($totalEmissionsKg, 2),
            'total_ton_co2e' => $totalTonCo2e,
            'reporting_lines_count' => count($emissionsByLine),
        ];
    }

    /**
     * Compute ecosystem health score per pillar (financial, operations, ESG, compliance)
     */
    public function computePillarHealthScore(float $finScore, float $opsScore, float $esgScore, float $complianceScore): array
    {
        // Composite weight: Fin 35%, Ops 25%, ESG 20%, Compliance 20%
        $composite = ($finScore * 0.35) + ($opsScore * 0.25) + ($esgScore * 0.20) + ($complianceScore * 0.20);
        $compositeRounded = round($composite, 1);

        $status = match (true) {
            $compositeRounded >= 85.0 => 'OPTIMAL',
            $compositeRounded >= 70.0 => 'HEALTHY',
            $compositeRounded >= 50.0 => 'NEEDS_ATTENTION',
            default => 'CRITICAL',
        };

        return [
            'composite_score' => $compositeRounded,
            'status' => $status,
        ];
    }
}
