<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\SmelterRun;
use Modules\Shared\Application\BaseAction;

class ExecuteSmelterMassBalanceRecoveryAction extends BaseAction
{
    /**
     * Compute RKEF / HPAL smelter mass balance, metal recovery efficiency, and byproduct slag monetization.
     *
     * @return array{smelter_run: SmelterRun, contained_metal_input_ton: float, recovery_rate_pct: float, byproduct_revenue_idr: int, is_mass_balance_conserved: bool}
     */
    public function handle(
        string $siteId,
        string $runCode,
        string $outputCommodity,
        float $inputOreTonnage,
        float $inputGradePct,
        float $outputMetalTonnage,
        float $byproductSlagTonnage,
        int $byproductRevenueIdr = 0,
        float $energyKwhPerTon = 3850.0
    ): array {
        return DB::transaction(function () use (
            $siteId,
            $runCode,
            $outputCommodity,
            $inputOreTonnage,
            $inputGradePct,
            $outputMetalTonnage,
            $byproductSlagTonnage,
            $byproductRevenueIdr,
            $energyKwhPerTon
        ) {
            $containedMetalInputTon = round(($inputOreTonnage * $inputGradePct) / 100, 3);

            if ($containedMetalInputTon <= 0) {
                throw new \InvalidArgumentException('Contained metal input must be positive.');
            }

            $recoveryRatePct = round(($outputMetalTonnage / $containedMetalInputTon) * 100, 2);

            // Mass conservation check: Output (metal + slag + moisture/loss) vs Input Ore
            // Total solid outputs (metal + slag) typically account for 85-98% of dry charge
            $totalOutputSolidTon = $outputMetalTonnage + $byproductSlagTonnage;
            $massRatio = $totalOutputSolidTon / $inputOreTonnage;
            $isMassBalanceConserved = ($massRatio >= 0.70 && $massRatio <= 1.05);

            $smelterRun = SmelterRun::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'run_code' => $runCode,
                'output_commodity' => $outputCommodity,
                'input_ore_tonnage' => $inputOreTonnage,
                'input_grade_pct' => $inputGradePct,
                'output_metal_tonnage' => $outputMetalTonnage,
                'recovery_rate_pct' => $recoveryRatePct,
                'byproduct_slag_tonnage' => $byproductSlagTonnage,
                'byproduct_revenue_idr' => $byproductRevenueIdr,
                'energy_kwh_per_ton' => $energyKwhPerTon,
                'status' => 'COMPLETED',
            ]);

            $this->audit(
                action: 'mining.smelter.mass_balance_executed',
                auditable: $smelterRun,
                oldValues: null,
                newValues: [
                    'run_code' => $runCode,
                    'output_commodity' => $outputCommodity,
                    'recovery_rate_pct' => $recoveryRatePct,
                    'byproduct_revenue_idr' => $byproductRevenueIdr,
                    'is_conserved' => $isMassBalanceConserved,
                ],
                correlationId: "min_smelt_{$runCode}",
                impactType: 'smelter_metallurgy_recovery'
            );

            return [
                'smelter_run' => $smelterRun,
                'contained_metal_input_ton' => $containedMetalInputTon,
                'recovery_rate_pct' => $recoveryRatePct,
                'byproduct_revenue_idr' => $byproductRevenueIdr,
                'is_mass_balance_conserved' => $isMassBalanceConserved,
            ];
        });
    }
}
