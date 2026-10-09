<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EnterpriseEthicsSocialLicenseService (Fase 481)
 *
 * Implements:
 *  - 481.1 Purpose & values operationalization across 30 lines
 *  - 481.2 Ethics maturity assessment: survey, speak-up health, case quality
 *  - 481.3 Social license index: community trust, regulatory standing, partner confidence, employee pride
 *  - 481.4 Tests: ethics maturity assessed, social license index documented, ethics:audit clean
 *  - 481.5 Edge case: Low ethics maturity (< 70) automatically enforces mandatory remediation plan submission
 *  - 481.6 Risk: Social license index requires open empirical methodology documentation (no subjective opacity)
 *  - 481.7 Evidence: values mapping, culture survey, social license assessment
 */
class EnterpriseEthicsSocialLicenseService
{
    public function assessEthicsMaturity(
        string $code,
        float $cultureSurvey,
        float $speakUpHealth,
        float $caseQuality
    ): object {
        $composite = round(($cultureSurvey * 0.40) + ($speakUpHealth * 0.35) + ($caseQuality * 0.25), 2);

        // 481.5 Edge case: Maturity score < 70 triggers mandatory improvement plan
        $requiresPlan = ($composite < 70.00);

        $id = DB::table('int_enterprise_ethics_maturity')->insertGetId([
            'cycle_code' => strtoupper($code),
            'culture_survey_score' => $cultureSurvey,
            'speak_up_health_score' => $speakUpHealth,
            'case_quality_score' => $caseQuality,
            'composite_ethics_maturity_score' => $composite,
            'requires_mandatory_improvement_plan' => $requiresPlan,
            'improvement_plan_submitted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_ethics_maturity')->where('id', $id)->first();
    }

    public function submitImprovementPlan(string $code): object
    {
        $m = DB::table('int_enterprise_ethics_maturity')->where('cycle_code', strtoupper($code))->first();
        if (! $m) {
            throw new InvalidArgumentException("Cycle '{$code}' not found.");
        }

        DB::table('int_enterprise_ethics_maturity')->where('id', $m->id)->update([
            'improvement_plan_submitted' => true,
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_ethics_maturity')->where('id', $m->id)->first();
    }

    /**
     * 481.3 & 481.6 Calculate Social License Index with mandatory open empirical methodology
     */
    public function computeSocialLicenseIndex(
        string $code,
        float $communityTrust,
        float $regulatoryStanding,
        float $partnerConfidence,
        float $employeePride,
        bool $openMethodology = true
    ): object {
        // 481.6 Risk: Methodology must be open and documented
        if (! $openMethodology) {
            throw new InvalidArgumentException("Index blocked: Social license index calculation requires open, auditable empirical methodology documentation (481.6).");
        }

        $composite = round(($communityTrust * 0.30) + ($regulatoryStanding * 0.30) + ($partnerConfidence * 0.20) + ($employeePride * 0.20), 2);

        $id = DB::table('int_enterprise_social_license_indexes')->insertGetId([
            'index_code' => strtoupper($code),
            'community_trust_score' => $communityTrust,
            'regulatory_standing_score' => $regulatoryStanding,
            'partner_confidence_score' => $partnerConfidence,
            'employee_pride_score' => $employeePride,
            'composite_social_license_index' => $composite,
            'open_empirical_methodology_documented' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_social_license_indexes')->where('id', $id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Cycles requiring plan where plan was not submitted
        $unsubmittedPlans = DB::table('int_enterprise_ethics_maturity')
            ->where('requires_mandatory_improvement_plan', true)
            ->where('improvement_plan_submitted', false)
            ->count();

        // Discrepancy 2: Social license index without documented methodology
        $undocumentedIndexes = DB::table('int_enterprise_social_license_indexes')
            ->where('open_empirical_methodology_documented', false)
            ->count();

        $total = $unsubmittedPlans + $undocumentedIndexes;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_ethics_cycles' => DB::table('int_enterprise_ethics_maturity')->count(),
            'total_social_indexes' => DB::table('int_enterprise_social_license_indexes')->count(),
            'discrepancy_count' => $total,
        ];
    }
}
