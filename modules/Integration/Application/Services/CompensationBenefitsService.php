<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CompensationBenefitsService (Fase 224)
 *
 * Implements:
 *  - 224.1 Job architecture & cross-country pay structure compliance (pay bands)
 *  - 224.2 Variable pay pool governance (bonus sum = pool, clawback upon restatement)
 *  - 224.3 Benefits administration (health, life, pension, flexible)
 *  - 224.4 Pay equity audit tracking with remediation planning
 *  - 224.6 Edge case: Cross-country employee transfer with effective date transition preserving historical records intact
 *  - 224.7 Retroactive pay correction via formal approval journal adjustment without modifying historical payslips
 */
class CompensationBenefitsService
{
    /**
     * Define or update grade band pay structure.
     */
    public function setPayStructure(
        string $gradeBand,
        string $countryCode,
        string $currency,
        float $minSalary,
        float $midSalary,
        float $maxSalary
    ): object {
        DB::table('hcm_pay_structures')->updateOrInsert(
            [
                'grade_band' => strtoupper($gradeBand),
                'country_code' => strtoupper($countryCode),
            ],
            [
                'currency' => strtoupper($currency),
                'min_salary' => $minSalary,
                'mid_salary' => $midSalary,
                'max_salary' => $maxSalary,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('hcm_pay_structures')
            ->where('grade_band', strtoupper($gradeBand))
            ->where('country_code', strtoupper($countryCode))
            ->first();
    }

    /**
     * Assign compensation ensuring pay band conformity (224.1 & 224.5).
     * Preserves previous historical records upon country/role changes (224.6 Edge Case).
     */
    public function assignCompensation(
        string $employeeId,
        string $gradeBand,
        string $countryCode,
        string $currency,
        float $baseSalary,
        string $effectiveFrom
    ): object {
        $structure = DB::table('hcm_pay_structures')
            ->where('grade_band', strtoupper($gradeBand))
            ->where('country_code', strtoupper($countryCode))
            ->first();

        if (! $structure) {
            throw new \InvalidArgumentException("Pay structure not defined for grade {$gradeBand} in country {$countryCode}.");
        }

        // Pay band compliance check (224.5)
        if ($baseSalary < $structure->min_salary || $baseSalary > $structure->max_salary) {
            throw new \InvalidArgumentException(
                "Base salary {$baseSalary} {$currency} violates pay band range [{$structure->min_salary} - {$structure->max_salary}] for grade {$gradeBand}."
            );
        }

        // Edge Case 224.6: Close previous active record with effective date; NEVER overwrite history
        DB::table('hcm_employee_compensations')
            ->where('employee_id', strtoupper($employeeId))
            ->where('is_current', true)
            ->update([
                'is_current' => false,
                'effective_to' => $effectiveFrom,
                'updated_at' => now(),
            ]);

        $id = DB::table('hcm_employee_compensations')->insertGetId([
            'employee_id' => strtoupper($employeeId),
            'grade_band' => strtoupper($gradeBand),
            'country_code' => strtoupper($countryCode),
            'currency' => strtoupper($currency),
            'base_salary' => $baseSalary,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_employee_compensations')->find($id);
    }

    /**
     * Initialize variable pay bonus pool.
     */
    public function createVariablePayPool(string $poolCode, float $totalPoolAmount): object
    {
        $id = DB::table('hcm_variable_pay_pools')->insertGetId([
            'pool_code' => strtoupper($poolCode),
            'total_pool_amount' => $totalPoolAmount,
            'distributed_amount' => 0,
            'status' => 'OPEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_variable_pay_pools')->find($id);
    }

    /**
     * Distribute variable pay payout ensuring bonus sum does not exceed pool (224.2 & 224.5).
     */
    public function distributePayout(string $poolCode, string $employeeId, float $payoutAmount): object
    {
        $pool = DB::table('hcm_variable_pay_pools')->where('pool_code', strtoupper($poolCode))->first();
        if (! $pool) {
            throw new \InvalidArgumentException("Bonus pool {$poolCode} not found.");
        }

        $remaining = (float) $pool->total_pool_amount - (float) $pool->distributed_amount;
        if ($payoutAmount > $remaining) {
            throw new \InvalidArgumentException(
                "Variable payout rejected: Requested {$payoutAmount} exceeds remaining pool budget {$remaining} (total pool: {$pool->total_pool_amount})."
            );
        }

        DB::table('hcm_variable_pay_pools')
            ->where('pool_code', strtoupper($poolCode))
            ->increment('distributed_amount', $payoutAmount);

        $payoutCode = 'PAYOUT-'.strtoupper(Str::random(8));

        $id = DB::table('hcm_variable_payouts')->insertGetId([
            'payout_code' => $payoutCode,
            'pool_code' => strtoupper($poolCode),
            'employee_id' => strtoupper($employeeId),
            'payout_amount' => $payoutAmount,
            'is_clawbacked' => false,
            'clawback_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_variable_payouts')->find($id);
    }

    /**
     * Variable pay clawback upon financial restatement (224.2).
     */
    public function clawbackPayout(string $payoutCode, string $reason): void
    {
        $payout = DB::table('hcm_variable_payouts')->where('payout_code', strtoupper($payoutCode))->first();
        if (! $payout) {
            throw new \InvalidArgumentException("Payout {$payoutCode} not found.");
        }

        if ($payout->is_clawbacked) {
            return;
        }

        DB::table('hcm_variable_payouts')
            ->where('payout_code', strtoupper($payoutCode))
            ->update([
                'is_clawbacked' => true,
                'clawback_reason' => $reason,
                'updated_at' => now(),
            ]);

        DB::table('hcm_variable_pay_pools')
            ->where('pool_code', $payout->pool_code)
            ->decrement('distributed_amount', $payout->payout_amount);
    }

    /**
     * Benefits administration enrollment (224.3).
     */
    public function enrollBenefit(
        string $employeeId,
        string $benefitType,
        float $monthlyPremium,
        string $enrolledAt
    ): object {
        $enrollCode = 'BEN-'.strtoupper(Str::random(8));

        $id = DB::table('hcm_benefit_enrollments')->insertGetId([
            'enrollment_code' => $enrollCode,
            'employee_id' => strtoupper($employeeId),
            'benefit_type' => strtoupper($benefitType),
            'monthly_premium' => $monthlyPremium,
            'status' => 'ACTIVE',
            'enrolled_at' => $enrolledAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_benefit_enrollments')->find($id);
    }

    /**
     * Request retroactive pay adjustment without editing old slips (224.7).
     */
    public function requestRetroactiveAdjustment(
        string $employeeId,
        string $targetPeriod,
        float $amount,
        string $reason
    ): object {
        $adjCode = 'ADJ-'.strtoupper(Str::random(8));

        $id = DB::table('hcm_retroactive_adjustments')->insertGetId([
            'adjustment_code' => $adjCode,
            'employee_id' => strtoupper($employeeId),
            'target_period' => $targetPeriod,
            'adjustment_amount' => $amount,
            'reason' => $reason,
            'approved_by' => null,
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_retroactive_adjustments')->find($id);
    }

    /**
     * Approve retroactive adjustment.
     */
    public function approveRetroactiveAdjustment(string $adjustmentCode, string $approvedBy): void
    {
        DB::table('hcm_retroactive_adjustments')
            ->where('adjustment_code', strtoupper($adjustmentCode))
            ->update([
                'status' => 'APPROVED',
                'approved_by' => $approvedBy,
                'updated_at' => now(),
            ]);
    }

    /**
     * Record periodic pay equity audit (224.4).
     */
    public function recordPayEquityAudit(
        string $cohortGroup,
        float $unadjustedGapPct,
        float $adjustedGapPct,
        ?string $remediationPlan = null
    ): object {
        $auditCode = 'PEA-'.strtoupper(Str::random(8));

        $id = DB::table('hcm_pay_equity_audits')->insertGetId([
            'audit_code' => $auditCode,
            'cohort_group' => $cohortGroup,
            'unadjusted_gap_pct' => $unadjustedGapPct,
            'adjusted_gap_pct' => $adjustedGapPct,
            'remediation_plan' => $remediationPlan,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_pay_equity_audits')->find($id);
    }

    /**
     * Quality audit gate (`hcm:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: Current salaries violating pay structure boundaries
        $bandViolations = DB::table('hcm_employee_compensations as c')
            ->join('hcm_pay_structures as s', function ($join) {
                $join->on('c.grade_band', '=', 's.grade_band')
                     ->on('c.country_code', '=', 's.country_code');
            })
            ->where('c.is_current', true)
            ->where(function ($q) {
                $q->whereColumn('c.base_salary', '<', 's.min_salary')
                  ->orWhereColumn('c.base_salary', '>', 's.max_salary');
            })
            ->count();

        // Discrepancy 2: Bonus pool over-distribution
        $poolViolations = DB::table('hcm_variable_pay_pools')
            ->whereRaw('distributed_amount > total_pool_amount')
            ->count();

        $discrepancies = $bandViolations + $poolViolations;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_active_compensations' => DB::table('hcm_employee_compensations')->where('is_current', true)->count(),
            'total_bonus_pools' => DB::table('hcm_variable_pay_pools')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
