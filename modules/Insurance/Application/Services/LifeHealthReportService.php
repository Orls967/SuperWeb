<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Insurance\Domain\Models\HealthClaim;
use Modules\Insurance\Domain\Models\HealthPolicy;
use Modules\Insurance\Domain\Models\LifePolicy;
use Modules\Insurance\Domain\Models\UnitLinkFund;
use Modules\Insurance\Domain\Models\UnitLinkHolding;

final class LifeHealthReportService
{
    /**
     * Menghasilkan ringkasan laporan aktuaria mortalitas dan portofolio polis jiwa.
     *
     * @return array<string, mixed>
     */
    public function generateLifeActuarialSummary(): array
    {
        $activePolicies = LifePolicy::where('status', 'active')->get();
        $totalPolicies = $activePolicies->count();
        $totalSumInsured = (int) $activePolicies->sum('sum_insured_idr');
        $totalAnnualPremium = (int) $activePolicies->sum('premium_annual_idr');

        $ageGroups = [
            '18-30' => ['count' => 0, 'exposure_idr' => 0],
            '31-45' => ['count' => 0, 'exposure_idr' => 0],
            '46-60' => ['count' => 0, 'exposure_idr' => 0],
            '61+' => ['count' => 0, 'exposure_idr' => 0],
        ];

        foreach ($activePolicies as $policy) {
            $age = $policy->insured_age;
            $group = match (true) {
                $age <= 30 => '18-30',
                $age <= 45 => '31-45',
                $age <= 60 => '46-60',
                default => '61+',
            };
            $ageGroups[$group]['count']++;
            $ageGroups[$group]['exposure_idr'] += (int) $policy->sum_insured_idr;
        }

        $smokerExposure = (int) $activePolicies->where('is_smoker', true)->sum('sum_insured_idr');
        $nonSmokerExposure = (int) $activePolicies->where('is_smoker', false)->sum('sum_insured_idr');

        return [
            'as_of_date' => CarbonImmutable::now()->toIso8601String(),
            'active_policies_count' => $totalPolicies,
            'total_sum_insured_idr' => $totalSumInsured,
            'total_annual_premium_idr' => $totalAnnualPremium,
            'average_sum_insured_idr' => $totalPolicies > 0 ? (int) intdiv($totalSumInsured, $totalPolicies) : 0,
            'age_distribution' => $ageGroups,
            'lifestyle_risk' => [
                'smoker_exposure_idr' => $smokerExposure,
                'non_smoker_exposure_idr' => $nonSmokerExposure,
                'smoker_percentage' => $totalSumInsured > 0 ? round(($smokerExposure / $totalSumInsured) * 100, 2) : 0.0,
            ],
        ];
    }

    /**
     * Menghasilkan analisis klaim kesehatan, rasio loss ratio dan distribusi klaim rawat inap/jalan.
     *
     * @return array<string, mixed>
     */
    public function generateHealthLossRatioReport(): array
    {
        $allPolicies = HealthPolicy::all();
        $totalEarnedPremium = (int) $allPolicies->sum('premium_annual_idr');

        $claims = HealthClaim::all();
        $incurredClaims = (int) $claims->whereIn('status', ['approved', 'settled'])->sum('approved_amount_idr');
        $pendingClaims = (int) $claims->where('status', 'submitted')->sum('claim_amount_idr');
        $rejectedClaims = (int) $claims->where('status', 'rejected')->sum('claim_amount_idr');

        $lossRatio = $totalEarnedPremium > 0
            ? round(($incurredClaims / $totalEarnedPremium) * 100, 2)
            : 0.0;

        $inpatientClaims = (int) $claims->where('treatment_type', 'inpatient')
            ->whereIn('status', ['approved', 'settled'])
            ->sum('approved_amount_idr');
        $outpatientClaims = (int) $claims->where('treatment_type', 'outpatient')
            ->whereIn('status', ['approved', 'settled'])
            ->sum('approved_amount_idr');

        return [
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'total_earned_premium_idr' => $totalEarnedPremium,
            'incurred_claims_idr' => $incurredClaims,
            'pending_claims_idr' => $pendingClaims,
            'rejected_claims_idr' => $rejectedClaims,
            'loss_ratio_percent' => $lossRatio,
            'is_healthy_ratio' => $lossRatio <= 75.0,
            'breakdown_by_treatment' => [
                'inpatient_idr' => $inpatientClaims,
                'outpatient_idr' => $outpatientClaims,
            ],
            'claim_count_status' => [
                'approved' => $claims->whereIn('status', ['approved', 'settled'])->count(),
                'pending' => $claims->where('status', 'submitted')->count(),
                'rejected' => $claims->where('status', 'rejected')->count(),
                'appealed' => $claims->where('status', 'appealed')->count(),
            ],
        ];
    }

    /**
     * Menghasilkan konsolidasi aset investasi unit link dan eksposur NAB per jenis dana.
     *
     * @return array<string, mixed>
     */
    public function generateUnitLinkConsolidatedReport(): array
    {
        $funds = UnitLinkFund::all();
        $holdings = UnitLinkHolding::all();

        $fundSummaries = [];
        $totalAumIdr = 0;

        foreach ($funds as $fund) {
            $fundHoldings = $holdings->where('fund_id', $fund->id);
            $totalUnits = (float) $fundHoldings->sum('units');
            $valuationIdr = (int) round($totalUnits * ($fund->latest_nav_idr / 100));
            $totalAumIdr += $valuationIdr;

            $fundSummaries[] = [
                'fund_code' => $fund->fund_code,
                'fund_name' => $fund->fund_name,
                'asset_class' => $fund->asset_class,
                'risk_level' => $fund->risk_level,
                'nav_idr' => $fund->latest_nav_idr,
                'nav_minor' => $fund->latest_nav_idr,
                'total_units' => round($totalUnits, 4),
                'valuation_idr' => $valuationIdr,
                'participant_count' => $fundHoldings->pluck('policy_id')->unique()->count(),
            ];
        }

        return [
            'total_aum_idr' => $totalAumIdr,
            'total_funds_count' => $funds->count(),
            'funds' => $fundSummaries,
            'reported_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}

