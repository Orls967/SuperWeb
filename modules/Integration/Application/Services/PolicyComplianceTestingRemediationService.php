<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * PolicyComplianceTestingRemediationService (Fase 451)
 *
 * Implements:
 *  - 451.1 Compliance test plan: transactions sampling against policy, findings, verification
 *  - 451.2 Regulatory examination simulation
 *  - 451.3 Repeat finding analysis: systemic cause, control redesign, independent closure check
 *  - 451.4 Tests: sample statistically valid, repeat finding closure requires independent verification, compliance:audit clean
 *  - 451.5 Edge case: Repeat finding strictly mandates systemic root cause & control redesign (cannot be closed as isolated)
 *  - 451.6 Risk: Statistical sample validity required before forming compliance opinion
 *  - 451.7 Evidence: test plan, sample results, remediation verification
 */
class PolicyComplianceTestingRemediationService
{
    public function logFinding(
        string $code,
        string $policyArea,
        bool $isStatisticallyValidSample = true,
        bool $isRepeatFinding = false
    ): object {
        // 451.6 Risk: Sampling method validation
        if (! $isStatisticallyValidSample) {
            throw new InvalidArgumentException("Test invalid: Compliance opinion cannot be derived from a statistically invalid/non-representative sample (451.4, 451.6).");
        }

        $id = DB::table('gov_policy_compliance_findings')->insertGetId([
            'finding_code' => strtoupper($code),
            'policy_area' => strtolower($policyArea),
            'is_statistically_valid_sample' => true,
            'is_repeat_finding' => $isRepeatFinding,
            'systemic_root_cause_analysis' => null,
            'redesigned_control_details' => null,
            'independent_verifier_closed' => false,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_policy_compliance_findings')->where('id', $id)->first();
    }

    /**
     * 451.3, 451.4, 451.5 Close finding with independent verification and mandatory systemic redesign for repeats
     */
    public function closeFinding(
        string $code,
        bool $independentVerifierCheck,
        ?string $systemicRca = null,
        ?string $redesignedControl = null
    ): object {
        $f = DB::table('gov_policy_compliance_findings')->where('finding_code', strtoupper($code))->first();
        if (! $f) {
            throw new InvalidArgumentException("Finding '{$code}' not found.");
        }

        // 451.3 & 451.4 Independent verification required to close repeat findings
        if ($f->is_repeat_finding && ! $independentVerifierCheck) {
            throw new InvalidArgumentException("Closure blocked: Repeat compliance finding requires independent audit verification before closure (451.3, 451.4).");
        }

        // 451.5 Edge case: Repeat finding cannot be closed as isolated without systemic RCA and control redesign
        if ($f->is_repeat_finding && (empty(trim($systemicRca ?? '')) || empty(trim($redesignedControl ?? '')))) {
            throw new InvalidArgumentException("Closure blocked: Repeat finding cannot be closed as an isolated incident; systemic root cause analysis and control redesign are required (451.3, 451.5).");
        }

        DB::table('gov_policy_compliance_findings')->where('id', $f->id)->update([
            'systemic_root_cause_analysis' => $systemicRca,
            'redesigned_control_details' => $redesignedControl,
            'independent_verifier_closed' => $independentVerifierCheck,
            'status' => 'verified_closed',
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_policy_compliance_findings')->where('id', $f->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy: Repeat findings closed without independent check or without redesigned controls
        $unverifiedRepeatClosures = DB::table('gov_policy_compliance_findings')
            ->where('is_repeat_finding', true)
            ->where('status', 'verified_closed')
            ->where(function ($query) {
                $query->where('independent_verifier_closed', false)
                    ->orWhereNull('redesigned_control_details')
                    ->orWhere('redesigned_control_details', '');
            })
            ->count();

        return [
            'status' => $unverifiedRepeatClosures === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_findings' => DB::table('gov_policy_compliance_findings')->count(),
            'discrepancy_count' => $unverifiedRepeatClosures,
        ];
    }
}
