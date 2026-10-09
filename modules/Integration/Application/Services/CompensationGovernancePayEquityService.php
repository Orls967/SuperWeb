<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * CompensationGovernancePayEquityService (Fase 422)
 *
 * Implements:
 *  - 422.1 Market benchmark refresh cycle and salary bands
 *  - 422.2 Pay review cycle: merit budget guardrails & calibration committee
 *  - 422.3 Pay equity analysis: unexplained gap flagging & remediation
 *  - 422.4 Tests: merit within budget and band, exception approval required, hcm:audit clean
 *  - 422.5 Edge case: Out-of-band salary increases strictly blocked without committee exception approval
 *  - 422.6 Risk: Unexplained equity gap requires mandatory remediation before report closure
 *  - 422.7 Evidence: benchmark snapshot, calibration minutes, equity analysis
 */
class CompensationGovernancePayEquityService
{
    public function registerSalaryBand(
        string $bandCode,
        string $jobFamily,
        string $gradeLevel,
        float $min,
        float $mid,
        float $max,
        string $provider = 'MERCER_SIMULATED'
    ): object {
        $id = DB::table('hcm_compensation_bands')->insertGetId([
            'band_code' => strtoupper($bandCode),
            'job_family' => strtolower($jobFamily),
            'grade_level' => strtoupper($gradeLevel),
            'min_salary' => $min,
            'mid_salary' => $mid,
            'max_salary' => $max,
            'benchmark_provider' => $provider,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_compensation_bands')->where('id', $id)->first();
    }

    public function processMeritReview(
        string $reviewCode,
        string $employeeId,
        string $bandCode,
        float $currentSalary,
        float $meritPercent,
        float $meritBudgetCap = 10.00,
        ?string $committeeApproval = null
    ): object {
        $band = DB::table('hcm_compensation_bands')->where('band_code', strtoupper($bandCode))->first();
        if (! $band) {
            throw new InvalidArgumentException("Salary band '{$bandCode}' not found.");
        }

        $proposedSalary = round($currentSalary * (1 + ($meritPercent / 100)), 2);

        // 422.4 Guardrail 1: Merit budget cap
        $withinBudget = $meritPercent <= $meritBudgetCap;
        if (! $withinBudget && empty($committeeApproval)) {
            throw new InvalidArgumentException("Merit blocked: Increase of {$meritPercent}% exceeds merit budget cap of {$meritBudgetCap}% without committee approval (422.2, 422.4).");
        }

        // 422.4 Guardrail 2: Salary band limits
        $withinBand = ($proposedSalary >= (float) $band->min_salary && $proposedSalary <= (float) $band->max_salary);
        if (! $withinBand && empty($committeeApproval)) {
            throw new InvalidArgumentException("Salary out of band: Proposed salary {$proposedSalary} is outside band limits ({$band->min_salary} - {$band->max_salary}) without committee approval (422.4, 422.5).");
        }

        $id = DB::table('hcm_merit_pay_reviews')->insertGetId([
            'review_code' => strtoupper($reviewCode),
            'employee_id' => $employeeId,
            'band_code' => strtoupper($bandCode),
            'current_salary' => $currentSalary,
            'proposed_salary' => $proposedSalary,
            'merit_increase_percent' => $meritPercent,
            'within_merit_budget' => $withinBudget,
            'within_salary_band' => $withinBand,
            'equity_gap_flagged' => false,
            'equity_remediation_completed' => true,
            'calibration_committee_approval' => $committeeApproval,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_merit_pay_reviews')->where('id', $id)->first();
    }

    /**
     * 422.3 & 422.6 Flag unexplained pay equity gap requiring remediation
     */
    public function flagUnexplainedEquityGap(string $reviewCode): object
    {
        $review = DB::table('hcm_merit_pay_reviews')->where('review_code', strtoupper($reviewCode))->first();
        if (! $review) {
            throw new InvalidArgumentException("Review '{$reviewCode}' not found.");
        }

        DB::table('hcm_merit_pay_reviews')->where('id', $review->id)->update([
            'equity_gap_flagged' => true,
            'equity_remediation_completed' => false,
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_merit_pay_reviews')->where('id', $review->id)->first();
    }

    public function completeEquityRemediation(string $reviewCode, float $adjustedSalary): object
    {
        $review = DB::table('hcm_merit_pay_reviews')->where('review_code', strtoupper($reviewCode))->first();
        if (! $review) {
            throw new InvalidArgumentException("Review '{$reviewCode}' not found.");
        }

        DB::table('hcm_merit_pay_reviews')->where('id', $review->id)->update([
            'proposed_salary' => $adjustedSalary,
            'equity_remediation_completed' => true,
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_merit_pay_reviews')->where('id', $review->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Reviews out of band or exceeding budget without committee approval
        $unapprovedOverrides = DB::table('hcm_merit_pay_reviews')
            ->where(function ($query) {
                $query->where('within_merit_budget', false)
                    ->orWhere('within_salary_band', false);
            })
            ->whereNull('calibration_committee_approval')
            ->count();

        // Discrepancy 2: Equity gaps not remediated (422.6)
        $unremediatedGaps = DB::table('hcm_merit_pay_reviews')
            ->where('equity_gap_flagged', true)
            ->where('equity_remediation_completed', false)
            ->count();

        $totalDiscrepancies = $unapprovedOverrides + $unremediatedGaps;

        return [
            'status' => $totalDiscrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'unapproved_overrides' => $unapprovedOverrides,
            'unremediated_gaps' => $unremediatedGaps,
            'discrepancy_count' => $totalDiscrepancies,
        ];
    }
}
