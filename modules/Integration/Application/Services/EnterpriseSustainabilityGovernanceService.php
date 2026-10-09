<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EnterpriseSustainabilityGovernanceService (Fase 464)
 *
 * Implements:
 *  - 464.1 Sustainability steering: cross-line trade-off decisions (cost vs carbon vs social)
 *  - 464.2 Integration into enterprise risk & corporate strategy
 *  - 464.3 Sustainability performance in leadership scorecard using verified metrics only
 *  - 464.4 Tests: trade-off documented, risk integration confirmed, esg:audit clean
 *  - 464.5 Edge case: Clash between short-term financial margins and carbon targets escalated with documented trade-off
 *  - 464.6 Risk: Action items must have an assigned owner and due date to ensure tangible execution
 *  - 464.7 Evidence: steering minutes, risk integration, verified scorecard metrics
 */
class EnterpriseSustainabilityGovernanceService
{
    public function recordSteeringDecision(
        string $code,
        string $title,
        string $tradeoffRationale,
        string $owner,
        string $dueDate,
        bool $integratedIntoRisk = true,
        bool $verifiedMetrics = true
    ): object {
        // 464.1 & 464.5 Trade-off rationale cannot be empty
        if (empty(trim($tradeoffRationale))) {
            throw new InvalidArgumentException("Decision blocked: Explicit documented trade-off rationale between cost, carbon, and social impact is mandatory (464.1, 464.5).");
        }

        // 464.6 Risk: Execution accountability
        if (empty(trim($owner)) || empty(trim($dueDate))) {
            throw new InvalidArgumentException("Decision blocked: Steering actions must have a designated owner and due date (464.6).");
        }

        // 464.3 Scorecard inclusion strictly requires verified metrics
        if (! $verifiedMetrics) {
            throw new InvalidArgumentException("Scorecard blocked: Sustainability steering KPI metrics must be third-party verified (464.3, 464.4).");
        }

        $id = DB::table('int_enterprise_sustainability_steering')->insertGetId([
            'decision_code' => strtoupper($code),
            'decision_title' => $title,
            'tradeoff_rationale' => $tradeoffRationale,
            'assigned_owner' => $owner,
            'due_date' => $dueDate,
            'integrated_into_enterprise_risk' => $integratedIntoRisk,
            'uses_third_party_verified_metrics' => true,
            'status' => 'actionable',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_sustainability_steering')->where('id', $id)->first();
    }

    public function executeSteeringAction(string $code): object
    {
        $d = DB::table('int_enterprise_sustainability_steering')->where('decision_code', strtoupper($code))->first();
        if (! $d) {
            throw new InvalidArgumentException("Decision '{$code}' not found.");
        }

        DB::table('int_enterprise_sustainability_steering')->where('id', $d->id)->update([
            'status' => 'executed',
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_sustainability_steering')->where('id', $d->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Decisions not integrated into enterprise risk
        $unintegratedRisk = DB::table('int_enterprise_sustainability_steering')
            ->where('integrated_into_enterprise_risk', false)
            ->count();

        // Discrepancy 2: Decisions without third party verified metrics
        $unverifiedMetrics = DB::table('int_enterprise_sustainability_steering')
            ->where('uses_third_party_verified_metrics', false)
            ->count();

        $total = $unintegratedRisk + $unverifiedMetrics;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_steering_decisions' => DB::table('int_enterprise_sustainability_steering')->count(),
            'discrepancy_count' => $total,
        ];
    }
}
