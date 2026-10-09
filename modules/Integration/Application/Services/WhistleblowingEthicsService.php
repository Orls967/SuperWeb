<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * WhistleblowingEthicsService (Fase 232)
 *
 * Implements:
 *  - 232.1 Anonymous speak-up channel & four-eyes investigator assignment
 *  - 232.2 Anti-retaliation monitoring & proactive anomaly alerts
 *  - 232.3 Ethics case management, sanction matrix & strict data isolation from HR views
 *  - 232.4 Fraud mesh referral bridge without duplicating investigations
 *  - 232.6 Edge case: Reporter identity leak triggers independent investigation & protection without halting core inquiry
 *  - 232.7 Anti-SLAPP legal defense support for whistleblowers
 */
class WhistleblowingEthicsService
{
    /**
     * Submit whistleblowing report preserving strict reporter anonymity (232.1 & 232.5).
     */
    public function submitReport(
        string $category,
        string $encryptedSummary,
        bool $isAnonymous = true,
        bool $hasFraudIndication = false
    ): object {
        $reportCode = 'WB-'.strtoupper(Str::random(8));
        $anonymousToken = 'TKN-'.hash('sha256', Str::random(16));

        $id = DB::table('gov_whistleblower_reports')->insertGetId([
            'report_code' => $reportCode,
            'anonymous_token' => $anonymousToken,
            'category' => strtoupper($category),
            'encrypted_summary' => $encryptedSummary,
            'primary_investigator_id' => null,
            'secondary_investigator_id' => null,
            'status' => 'SUBMITTED',
            'is_anonymous' => $isAnonymous,
            'identity_leaked' => false,
            'has_fraud_indication' => $hasFraudIndication,
            'fraud_mesh_case_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_whistleblower_reports')->find($id);
    }

    /**
     * Assign dual investigators enforcing the four-eyes principle (232.1 & 232.5).
     */
    public function assignInvestigators(
        string $reportCode,
        string $primaryInvestigator,
        string $secondaryInvestigator
    ): object {
        if (strtoupper($primaryInvestigator) === strtoupper($secondaryInvestigator)) {
            throw new \InvalidArgumentException(
                "Four-eyes principle violation: Primary and secondary investigators must be two distinct individuals."
            );
        }

        DB::table('gov_whistleblower_reports')
            ->where('report_code', strtoupper($reportCode))
            ->update([
                'primary_investigator_id' => strtoupper($primaryInvestigator),
                'secondary_investigator_id' => strtoupper($secondaryInvestigator),
                'status' => 'INVESTIGATING',
                'updated_at' => now(),
            ]);

        return (object) DB::table('gov_whistleblower_reports')->where('report_code', strtoupper($reportCode))->first();
    }

    /**
     * Bridge report to fraud mesh without duplicating inquiry (232.4).
     */
    public function referToFraudMesh(string $reportCode): object
    {
        $meshCaseCode = 'FRD-MESH-'.strtoupper(Str::random(8));

        DB::table('gov_whistleblower_reports')
            ->where('report_code', strtoupper($reportCode))
            ->update([
                'has_fraud_indication' => true,
                'fraud_mesh_case_id' => $meshCaseCode,
                'updated_at' => now(),
            ]);

        return (object) DB::table('gov_whistleblower_reports')->where('report_code', strtoupper($reportCode))->first();
    }

    /**
     * Handle identity leak edge case (232.6 Edge Case).
     * Triggers protective anti-retaliation alert while allowing core investigation to continue.
     */
    public function reportIdentityLeak(string $reportCode, string $breachDetails): object
    {
        $report = DB::table('gov_whistleblower_reports')->where('report_code', strtoupper($reportCode))->first();
        if (! $report) {
            throw new \InvalidArgumentException("Report {$reportCode} not found.");
        }

        DB::table('gov_whistleblower_reports')
            ->where('report_code', strtoupper($reportCode))
            ->update([
                'identity_leaked' => true,
                'updated_at' => now(),
            ]);

        // Auto-trigger protective retaliation alert
        $this->triggerRetaliationAlert(
            $reportCode,
            'IDENTITY_LEAK_BREACH: '.$breachDetails,
            true // Enable legal protection
        );

        return (object) DB::table('gov_whistleblower_reports')->where('report_code', strtoupper($reportCode))->first();
    }

    /**
     * Trigger anti-retaliation protective investigation & anti-SLAPP support (232.2 & 232.7).
     */
    public function triggerRetaliationAlert(
        string $reportCode,
        string $anomalyType,
        bool $provideAntiSlappSupport = false
    ): object {
        $alertCode = 'RET-'.strtoupper(Str::random(8));

        $id = DB::table('gov_anti_retaliation_alerts')->insertGetId([
            'alert_code' => $alertCode,
            'report_code' => strtoupper($reportCode),
            'reporter_token_or_id' => 'REPORTER-PROTECTED-REF',
            'treatment_anomaly_type' => $anomalyType,
            'investigation_status' => 'TRIGGERED',
            'legal_support_provided' => $provideAntiSlappSupport,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_anti_retaliation_alerts')->find($id);
    }

    /**
     * Apply ethics sanctions strictly isolated from regular HR employee view (232.3).
     */
    public function applySanction(
        string $reportCode,
        string $subjectPersonId,
        string $severity,
        string $sanctionApplied
    ): object {
        $code = 'SNC-'.strtoupper(Str::random(8));

        $id = DB::table('gov_ethics_sanctions')->insertGetId([
            'sanction_code' => $code,
            'report_code' => strtoupper($reportCode),
            'subject_person_id' => strtoupper($subjectPersonId),
            'violation_severity' => strtoupper($severity),
            'sanction_applied' => strtoupper($sanctionApplied),
            'appeal_status' => 'NONE',
            'isolated_from_hr_view' => true, // Strictly isolated from general HR records
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_ethics_sanctions')->find($id);
    }

    /**
     * Quality audit gate (`ethics:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: In-flight investigations missing four-eyes dual investigators
        $missingDualInvestigators = DB::table('gov_whistleblower_reports')
            ->where('status', 'INVESTIGATING')
            ->where(function ($q) {
                $q->whereNull('primary_investigator_id')
                  ->orWhereNull('secondary_investigator_id')
                  ->orWhereColumn('primary_investigator_id', '=', 'secondary_investigator_id');
            })
            ->count();

        // Discrepancy 2: Leaked identities lacking anti-retaliation protection alerts
        $reportsLeaked = DB::table('gov_whistleblower_reports')
            ->where('identity_leaked', true)
            ->pluck('report_code');

        $unprotectedLeaks = 0;
        foreach ($reportsLeaked as $code) {
            $hasAlert = DB::table('gov_anti_retaliation_alerts')->where('report_code', $code)->exists();
            if (! $hasAlert) {
                $unprotectedLeaks++;
            }
        }

        // Discrepancy 3: Sanctions unisolated from HR view
        $unisolatedSanctions = DB::table('gov_ethics_sanctions')
            ->where('isolated_from_hr_view', false)
            ->count();

        $discrepancies = $missingDualInvestigators + $unprotectedLeaks + $unisolatedSanctions;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_reports' => DB::table('gov_whistleblower_reports')->count(),
            'total_retaliation_alerts' => DB::table('gov_anti_retaliation_alerts')->count(),
            'total_sanctions' => DB::table('gov_ethics_sanctions')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
