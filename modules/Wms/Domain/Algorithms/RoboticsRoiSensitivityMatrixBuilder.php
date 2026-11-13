<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use Modules\Wms\Domain\ValueObjects\RationalRate;

/**
 * Pembangun matriks sensitivitas NPV multi-titik untuk proposal ROI otomasi (D279-7, J3).
 *
 * Kompleksitas: O(R * C * T) dengan R = jumlah titik diskonto, C = jumlah variasi capex, T = horizon tahun.
 * Aritmetika: 100% rasional eksak via AutomationNpvCalculator dan integer bps (tanpa float).
 */
final readonly class RoboticsRoiSensitivityMatrixBuilder
{
    public function __construct(
        private AutomationNpvCalculator $calculator,
    ) {}

    /**
     * @param  list<int>  $cashFlows
     * @return array{
     *     base_bps: int,
     *     grid: list<array{
     *         rate_bps: int,
     *         capex_multiplier_pct: int,
     *         investment_minor: int,
     *         npv_minor: int,
     *         payback_months: int|null,
     *         recommendation: string
     *     }>
     * }
     */
    public function buildGrid(
        int $investmentMinor,
        array $cashFlows,
        RationalRate $baseRate,
    ): array {
        $rateShiftsBps = [-500, 0, 500];
        $capexMultipliersPct = [90, 100, 115];
        $grid = [];

        foreach ($rateShiftsBps as $shift) {
            $shiftedRate = $baseRate->shiftByBasisPoints($shift);
            foreach ($capexMultipliersPct as $multPct) {
                $adjInvestment = intdiv($investmentMinor * $multPct, 100);
                $eval = $this->calculator->evaluate(
                    $adjInvestment,
                    $cashFlows,
                    $shiftedRate->numerator,
                    $shiftedRate->denominator,
                );

                $grid[] = [
                    'rate_bps' => $shiftedRate->toBasisPoints(),
                    'capex_multiplier_pct' => $multPct,
                    'investment_minor' => $adjInvestment,
                    'npv_minor' => $eval['npv'],
                    'payback_months' => $eval['payback_months'],
                    'recommendation' => $eval['recommendation'],
                ];
            }
        }

        return [
            'base_bps' => $baseRate->toBasisPoints(),
            'grid' => $grid,
        ];
    }
}
