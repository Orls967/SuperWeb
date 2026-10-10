<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EthicsComplianceMaturityService (Fase 337)
 *
 * Implements:
 *  - 337.1 Compliance program scorecard per line with weighted maturity metrics
 *  - 337.2 Third-party vendor ethics assessment, speak-up access, and termination rights
 *  - 337.4 Tests: Weighted scorecard criteria verified; vendor speak-up tested; ethics:audit clean
 *  - 337.5 Edge case: High maturity score (> 85) accompanied by frequent incidents (> 5) flagged as INCONSISTENT_ANOMALY triggering formal investigation
 *  - 337.6 Risk: Controls audited via actual sampling rather than paper formalities
 */
class EthicsComplianceMaturityService
{
    /**
     * Compute and record line compliance maturity scorecard with consistency anomaly detection (337.1, 337.4, 337.5 Edge Case).
     */
    public function recordLineComplianceScorecard(
        string $scorecardCode,
        string $lineCode,
        float $trainingScore,
        float $monitoringScore,
        float $remediationScore,
        int $incidentCount = 0
    ): object {
        $sCode = strtoupper($scorecardCode);
        $lCode = strtoupper($lineCode);

        // Weighted maturity score: 30% training + 40% monitoring + 30% remediation
        $weighted = round(($trainingScore * 0.30) + ($monitoringScore * 0.40) + ($remediationScore * 0.30), 1);

        // Edge case 337.5: High score (> 85) with high incidents (> 5) flags inconsistency anomaly
        $isAnomaly = ($weighted >= 85.0 && $incidentCount >= 5);
        $tier = $isAnomaly
            ? 'INCONSISTENT_ANOMALY'
            : ($weighted >= 80.0 ? 'MATURE' : 'DEVELOPING');

        $id = DB::table('compliance_program_line_scorecards')->insertGetId([
            'scorecard_code' => $sCode,
            'line_code' => $lCode,
            'weighted_maturity_score' => $weighted,
            'annual_ethics_incident_count' => $incidentCount,
            'flagged_for_consistency_investigation' => $isAnomaly,
            'maturity_tier' => $tier,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('compliance_program_line_scorecards')->find($id);
    }

    /**
     * Adjudicate third-party vendor ethics breach and exercise contractual termination right (337.2 & 337.4).
     */
    public function adjudicateVendorEthicsBreach(
        string $caseCode,
        string $vendorId,
        string $incidentType,
        bool $speakUpChannelVerified,
        bool $violationSubstantiated,
        bool $exerciseTermination = false
    ): object {
        $cCode = strtoupper($caseCode);
        $vId = strtoupper($vendorId);

        // Channel verification gate 337.4: Speak-up channel must be tested & verified
        if (! $speakUpChannelVerified) {
            throw new InvalidArgumentException('Ethics compliance breach: Vendor case processing requires verified speak-up hotline channel (337.4).');
        }

        // Termination validation 337.2: Termination requires substantiated violation evidence
        if ($exerciseTermination && ! $violationSubstantiated) {
            throw new InvalidArgumentException('Due process violation: Contractual termination right requires substantiated violation evidence (337.2).');
        }

        $id = DB::table('third_party_vendor_ethics_cases')->insertGetId([
            'case_code' => $cCode,
            'vendor_id' => $vId,
            'incident_type' => strtoupper($incidentType),
            'vendor_speak_up_channel_verified' => true,
            'has_admitted_or_substantiated_violation' => $violationSubstantiated,
            'contract_termination_exercised' => $exerciseTermination,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('third_party_vendor_ethics_cases')->find($id);
    }

    /**
     * Enterprise Ethics & Compliance Audit (`ethics:audit`) (337.4, 337.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: High maturity with high incidents not flagged for investigation
        $unflaggedAnomalies = DB::table('compliance_program_line_scorecards')
            ->where('weighted_maturity_score', '>=', 85.0)
            ->where('annual_ethics_incident_count', '>=', 5)
            ->where('flagged_for_consistency_investigation', false)
            ->count();

        // Discrepancy 2: Contract terminated without substantiated violation
        $unsupportedTerminations = DB::table('third_party_vendor_ethics_cases')
            ->where('contract_termination_exercised', true)
            ->where('has_admitted_or_substantiated_violation', false)
            ->count();

        $discrepancies = $unflaggedAnomalies + $unsupportedTerminations;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_scorecards' => DB::table('compliance_program_line_scorecards')->count(),
            'total_vendor_cases' => DB::table('third_party_vendor_ethics_cases')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
