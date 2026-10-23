<?php

declare(strict_types=1);

namespace Modules\Proptech\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Proptech\Domain\Models\PrescriptiveOp;
use Modules\Shared\Application\BaseAction;

class CalculateGreenBuildingRetrofitRoiAction extends BaseAction
{
    /**
     * Calculate Green Building Retrofit ROI, carbon offset, and payback period.
     *
     * @return array{zone_id: int, investment_cost_idr: int, monthly_savings_idr: int, annual_carbon_offset_kg: float, payback_months: int, five_year_roi_pct: float, prescriptive_op: PrescriptiveOp}
     */
    public function handle(
        int $zoneId,
        int $investmentCostIdr,
        float $expectedKwhSavingsMonthly
    ): array {
        return DB::transaction(function () use ($zoneId, $investmentCostIdr, $expectedKwhSavingsMonthly) {
            $zone = BuildingZone::findOrFail($zoneId);
            $kwhRate = (int) ($zone->current_kwh_rate ?: 1500);

            $monthlySavingsIdr = (int) round($expectedKwhSavingsMonthly * $kwhRate);
            $annualCarbonOffsetKg = (float) round($expectedKwhSavingsMonthly * 12 * 0.794, 2); // 0.794 kg CO2e / kWh in Indonesia grid

            $paybackMonths = $monthlySavingsIdr > 0
                ? (int) ceil($investmentCostIdr / $monthlySavingsIdr)
                : 999;

            $fiveYearTotalSavingsIdr = $monthlySavingsIdr * 60;
            $netBenefitIdr = $fiveYearTotalSavingsIdr - $investmentCostIdr;
            $fiveYearRoiPct = $investmentCostIdr > 0
                ? (float) round(($netBenefitIdr / $investmentCostIdr) * 100, 2)
                : 0.0;

            $actionCode = 'ACT-RETRO-'.$zone->id.'-'.date('YmdHis');
            $prescriptiveOp = PrescriptiveOp::create([
                'zone_id' => $zone->id,
                'action_code' => $actionCode,
                'recommendation_text' => "Retrofit HVAC inverter & LED arrays: save {$expectedKwhSavingsMonthly} kWh/mo, offset {$annualCarbonOffsetKg} kg CO2/yr.",
                'potential_kwh_saved' => $expectedKwhSavingsMonthly,
                'potential_saving_idr' => $monthlySavingsIdr,
                'payback_months' => $paybackMonths,
                'status' => 'PROPOSED',
            ]);

            $this->audit(
                action: 'proptech.retrofit.roi_evaluated',
                auditable: $prescriptiveOp,
                oldValues: null,
                newValues: [
                    'zone_id' => $zone->id,
                    'investment_cost_idr' => $investmentCostIdr,
                    'monthly_savings_idr' => $monthlySavingsIdr,
                    'payback_months' => $paybackMonths,
                    'five_year_roi_pct' => $fiveYearRoiPct,
                ],
                correlationId: "prp_roi_{$actionCode}",
                impactType: 'green_building_esg'
            );

            return [
                'zone_id' => $zone->id,
                'investment_cost_idr' => $investmentCostIdr,
                'monthly_savings_idr' => $monthlySavingsIdr,
                'annual_carbon_offset_kg' => $annualCarbonOffsetKg,
                'payback_months' => $paybackMonths,
                'five_year_roi_pct' => $fiveYearRoiPct,
                'prescriptive_op' => $prescriptiveOp,
            ];
        });
    }
}
