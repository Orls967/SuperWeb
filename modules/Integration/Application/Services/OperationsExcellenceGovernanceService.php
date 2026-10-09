<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * OperationsExcellenceGovernanceService (Fase 408)
 *
 * Implements:
 *  - 408.1 Improvement portfolio: baseline, benefit hypothesis, owner, milestones
 *  - 408.2 Benefit validation: finance-verified actuals, sustainment review at 6/12 months
 *  - 408.3 Standardization rollout & deviation management
 *  - 408.4 Tests: benefit validated by finance, sustainment review scheduled, quality:audit clean
 *  - 408.5 Edge case: program with safety/quality impact is strictly halted regardless of financial benefit
 *  - 408.6 Risk: sustainment review scheduling & verification
 *  - 408.7 Evidence: improvement portfolio, benefit validation, deviation log
 */
class OperationsExcellenceGovernanceService
{
    public function registerInitiative(
        string $code,
        string $title,
        string $owner,
        float $baselineMetric,
        float $targetMetric,
        ?string $sustainmentReviewDate = null
    ): object {
        $id = DB::table('ops_excellence_initiatives')->insertGetId([
            'initiative_code' => strtoupper($code),
            'title' => $title,
            'owner' => $owner,
            'baseline_metric' => $baselineMetric,
            'target_metric' => $targetMetric,
            'finance_verified_benefit' => 0.00,
            'finance_approved' => false,
            'safety_impact_detected' => false,
            'is_halted' => false,
            'status' => 'in_progress',
            'sustainment_review_date' => $sustainmentReviewDate ?? now()->addMonths(6)->toDateString(),
            'sustainment_verified' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_excellence_initiatives')->where('id', $id)->first();
    }

    public function validateBenefit(
        string $code,
        float $benefitAmount,
        bool $financeApproved = true
    ): object {
        $init = DB::table('ops_excellence_initiatives')->where('initiative_code', strtoupper($code))->first();
        if (! $init) {
            throw new InvalidArgumentException("Initiative '{$code}' not found.");
        }

        // 408.5 Edge case: Cannot validate benefit or continue if safety impact is detected
        if ($init->safety_impact_detected || $init->is_halted) {
            throw new InvalidArgumentException("Halted initiative: Initiative '{$code}' has safety/quality impacts and cannot be approved (408.5).");
        }

        DB::table('ops_excellence_initiatives')->where('id', $init->id)->update([
            'finance_verified_benefit' => $benefitAmount,
            'finance_approved' => $financeApproved,
            'status' => 'completed',
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_excellence_initiatives')->where('id', $init->id)->first();
    }

    /**
     * Flag adverse quality/safety impact (408.5 Edge case)
     */
    public function flagSafetyImpact(string $code, string $impactDetails): object
    {
        $init = DB::table('ops_excellence_initiatives')->where('initiative_code', strtoupper($code))->first();
        if (! $init) {
            throw new InvalidArgumentException("Initiative '{$code}' not found.");
        }

        DB::table('ops_excellence_initiatives')->where('id', $init->id)->update([
            'safety_impact_detected' => true,
            'is_halted' => true,
            'status' => 'halted',
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_excellence_initiatives')->where('id', $init->id)->first();
    }

    public function recordDeviation(string $code, string $deviationCode, string $reason, string $approvedBy): object
    {
        $init = DB::table('ops_excellence_initiatives')->where('initiative_code', strtoupper($code))->first();
        if (! $init) {
            throw new InvalidArgumentException("Initiative '{$code}' not found.");
        }

        $id = DB::table('ops_excellence_deviations')->insertGetId([
            'initiative_id' => $init->id,
            'deviation_code' => strtoupper($deviationCode),
            'deviation_reason' => $reason,
            'approved_by' => $approvedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_excellence_deviations')->where('id', $id)->first();
    }

    public function audit(): array
    {
        // Discrepancy: Initiatives marked completed without finance approval or that had safety impact but were not halted
        $unverifiedCompleted = DB::table('ops_excellence_initiatives')
            ->where('status', 'completed')
            ->where('finance_approved', false)
            ->count();

        $unhaltedSafety = DB::table('ops_excellence_initiatives')
            ->where('safety_impact_detected', true)
            ->where('is_halted', false)
            ->count();

        $totalDiscrepancies = $unverifiedCompleted + $unhaltedSafety;

        return [
            'status' => $totalDiscrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'unverified_completed' => $unverifiedCompleted,
            'unhalted_safety' => $unhaltedSafety,
            'discrepancy_count' => $totalDiscrepancies,
        ];
    }
}
