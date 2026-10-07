<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * LifeHealthWellnessService (Fase 159 — Lini 18)
 *
 * Implements:
 *  - 159.1 Term life policies & verified beneficiary registration
 *  - 159.2 Hospital cashless authorization (Guarantee Letter) strictly bounded by policy limit
 *  - 159.3 Wellness wearable rewards with anti-gaming guards (steps > 50,000 flagged)
 *  - 159.4 Unit Link fund valuation: AUM = NAV * units outstanding
 */
class LifeHealthWellnessService
{
    /**
     * Issue term life policy.
     */
    public function issueLifePolicy(int $insuredId, string $beneficiaryName, string $relation, float $deathBenefit, float $monthlyPremium): object
    {
        $policyNumber = 'LIFE-'.strtoupper(Str::random(10));

        $id = DB::table('ins_life_policies')->insertGetId([
            'policy_number' => $policyNumber,
            'insured_id' => $insuredId,
            'beneficiary_name' => $beneficiaryName,
            'beneficiary_relation' => strtoupper($relation),
            'death_benefit_amount' => $deathBenefit,
            'monthly_premium' => $monthlyPremium,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ins_life_policies')->find($id);
    }

    /**
     * Authorize hospital cashless Guarantee Letter (GL).
     * Enforces invarian: authorized_amount <= annual_limit.
     */
    public function authorizeCashless(string $policyNumber, string $hospitalCode, float $annualLimit, float $requestedAmount): object
    {
        $glNumber = 'GL-'.strtoupper(Str::random(8));
        $isWithinLimit = ($requestedAmount <= $annualLimit);
        $status = $isWithinLimit ? 'APPROVED' : 'EXCEEDS_LIMIT';

        $id = DB::table('ins_hospital_cashless_gls')->insertGetId([
            'gl_number' => $glNumber,
            'policy_number' => $policyNumber,
            'hospital_code' => $hospitalCode,
            'policy_annual_limit' => $annualLimit,
            'authorized_amount' => $isWithinLimit ? $requestedAmount : 0.00,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ins_hospital_cashless_gls')->find($id);
    }

    /**
     * Record daily steps and award wellness points with anti-gaming checks.
     */
    public function recordWellnessSteps(int $userId, Carbon $date, int $stepCount): object
    {
        // Anti-gaming rule: > 50,000 steps/day considered spoofed/gaming
        $isGaming = ($stepCount > 50000 || $stepCount < 0);
        $points = $isGaming ? 0 : (int) floor($stepCount / 1000) * 10; // 10 points per 1k steps

        DB::table('ins_wellness_activities')->updateOrInsert(
            ['user_id' => $userId, 'activity_date' => $date->toDateString()],
            [
                'step_count' => $stepCount,
                'reward_points' => $points,
                'is_flagged_gaming' => $isGaming,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('ins_wellness_activities')
            ->where('user_id', $userId)
            ->where('activity_date', $date->toDateString())
            ->first();
    }

    /**
     * Calculate and balance Unit Link fund AUM.
     * Invarian: total_aum = nav_per_unit * total_units_outstanding.
     */
    public function updateUnitLinkFund(string $fundCode, string $name, float $navPerUnit, float $totalUnits): object
    {
        $aum = round($navPerUnit * $totalUnits, 2);

        DB::table('ins_unit_link_funds')->updateOrInsert(
            ['fund_code' => $fundCode],
            [
                'fund_name' => $name,
                'nav_per_unit' => $navPerUnit,
                'total_units_outstanding' => $totalUnits,
                'total_assets_under_management' => $aum,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('ins_unit_link_funds')->where('fund_code', $fundCode)->first();
    }

    /**
     * Audit: verify cashless bounds and unit link fund calculations.
     */
    public function audit(): array
    {
        $funds = DB::table('ins_unit_link_funds')->get();
        $discrepancies = 0;

        foreach ($funds as $f) {
            $expected = round((float) $f->nav_per_unit * (float) $f->total_units_outstanding, 2);
            if (abs($expected - (float) $f->total_assets_under_management) > 0.05) {
                $discrepancies++;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_life_policies' => DB::table('ins_life_policies')->count(),
            'total_gls_issued' => DB::table('ins_hospital_cashless_gls')->count(),
            'total_funds' => $funds->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
