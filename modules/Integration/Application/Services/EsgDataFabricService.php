<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * EsgDataFabricService (Fase 228)
 *
 * Implements:
 *  - 228.1 ESG data fabric collecting metrics across 30 lines with provenance & quality score
 *  - 228.2 Double materiality assessment (impact + financial materiality) & committee scope review
 *  - 228.3 Reporting standards bridge (GRI / ISSB) mapping metrics to evidence
 *  - 228.5 Tests: Line metrics sum = group aggregate, gap never marked compliant
 *  - 228.5 Edge case: Failed metric collection flagged as data gap + alert (strictly forbids silent estimation)
 *  - 228.7 Low quality score metrics (< 0.70) strictly prohibited from public claims
 */
class EsgDataFabricService
{
    /**
     * Record ESG metric with provenance and quality verification (228.1 & 228.7).
     */
    public function recordMetric(
        string $businessLine,
        string $pillar,
        string $metricName,
        string $unitOfMeasure,
        ?float $reportedValue,
        string $provenanceSource,
        float $qualityScore,
        string $reportingPeriod,
        bool $isSensorDown = false,
        ?string $gapAlertReason = null
    ): object {
        $code = 'ESG-'.strtoupper(Str::random(8));

        // Edge Case 228.5: sensor down -> do NOT estimate silently, flag as data gap + alert
        $isDataGap = $isSensorDown || ($reportedValue === null);
        $finalValue = $isDataGap ? null : $reportedValue;
        $gapReason = $isDataGap ? ($gapAlertReason ?? 'Telemetry sensor signal lost') : null;

        // 228.7 Data quality score ESG per metric: low quality scores (< 0.70) disqualified from public claims
        $publicEligible = (! $isDataGap) && ($qualityScore >= 0.70);

        $id = DB::table('esg_metrics')->insertGetId([
            'metric_code' => $code,
            'business_line' => strtoupper($businessLine),
            'pillar' => strtoupper($pillar),
            'metric_name' => $metricName,
            'unit_of_measure' => $unitOfMeasure,
            'reported_value' => $finalValue,
            'is_data_gap' => $isDataGap,
            'gap_reason' => $gapReason,
            'provenance_source' => strtoupper($provenanceSource),
            'quality_score' => $qualityScore,
            'public_disclosure_eligible' => $publicEligible,
            'reporting_period' => strtoupper($reportingPeriod),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_metrics')->find($id);
    }

    /**
     * Double materiality assessment (228.2).
     */
    public function assessDoubleMateriality(
        string $businessLine,
        string $topicCode,
        string $topicName,
        float $impactScore,
        float $financialScore,
        bool $committeeReviewed = true
    ): object {
        // Material if either impact or financial score is >= 3.5
        $isMaterial = ($impactScore >= 3.5 || $financialScore >= 3.5);
        $inScope = $isMaterial && $committeeReviewed;

        DB::table('esg_materiality_topics')->updateOrInsert(
            ['topic_code' => strtoupper($topicCode)],
            [
                'business_line' => strtoupper($businessLine),
                'topic_name' => $topicName,
                'impact_materiality_score' => $impactScore,
                'financial_materiality_score' => $financialScore,
                'is_material' => $isMaterial,
                'in_reporting_scope' => $inScope,
                'committee_reviewed' => $committeeReviewed,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('esg_materiality_topics')->where('topic_code', strtoupper($topicCode))->first();
    }

    /**
     * Map metric to standard disclosure requirement (228.3 & 228.5: Gap never recorded as compliant).
     */
    public function mapDisclosure(
        string $standardCode,
        string $disclosureReqCode,
        string $metricCode,
        ?string $evidenceAttachmentUri = null
    ): object {
        $metric = DB::table('esg_metrics')->where('metric_code', strtoupper($metricCode))->first();
        if (! $metric) {
            throw new \InvalidArgumentException("Metric {$metricCode} not found.");
        }

        // 228.5: Evidence must exist and metric must NOT have a data gap to be marked COMPLIANT
        $isCompliant = (! $metric->is_data_gap) && ($metric->reported_value !== null) && ($evidenceAttachmentUri !== null);
        $status = $isCompliant ? 'COMPLIANT' : 'GAP_IDENTIFIED';

        $id = DB::table('esg_disclosure_mappings')->insertGetId([
            'standard_code' => strtoupper($standardCode),
            'disclosure_req_code' => strtoupper($disclosureReqCode),
            'metric_code' => strtoupper($metricCode),
            'compliance_status' => $status,
            'evidence_attachment_uri' => $evidenceAttachmentUri,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_disclosure_mappings')->find($id);
    }

    /**
     * Group aggregate metric across business lines (228.5: Sum of lines = group aggregate).
     */
    public function getGroupMetricAggregation(string $metricName, string $reportingPeriod): array
    {
        $metrics = DB::table('esg_metrics')
            ->where('metric_name', $metricName)
            ->where('reporting_period', strtoupper($reportingPeriod))
            ->get();

        $validValues = $metrics->where('is_data_gap', false)->pluck('reported_value');
        $sum = $validValues->sum();
        $gapsCount = $metrics->where('is_data_gap', true)->count();

        return [
            'metric_name' => $metricName,
            'reporting_period' => strtoupper($reportingPeriod),
            'total_lines_reported' => $metrics->count(),
            'data_gaps_count' => $gapsCount,
            'aggregated_sum' => round((float) $sum, 4),
        ];
    }

    /**
     * Quality audit gate (`esg:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: Low quality score (< 0.70) or data gap falsely eligible for public disclosure
        $illegalPublicClaims = DB::table('esg_metrics')
            ->where('public_disclosure_eligible', true)
            ->where(function ($q) {
                $q->where('quality_score', '<', 0.70)
                    ->orWhere('is_data_gap', true)
                    ->orWhereNull('reported_value');
            })
            ->count();

        // Discrepancy 2: Disclosure mapping marked COMPLIANT when metric is a data gap or lacks evidence
        $falseCompliantDisclosures = DB::table('esg_disclosure_mappings as d')
            ->join('esg_metrics as m', 'd.metric_code', '=', 'm.metric_code')
            ->where('d.compliance_status', 'COMPLIANT')
            ->where(function ($q) {
                $q->where('m.is_data_gap', true)
                    ->orWhereNull('m.reported_value')
                    ->orWhereNull('d.evidence_attachment_uri');
            })
            ->count();

        $discrepancies = $illegalPublicClaims + $falseCompliantDisclosures;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_metrics' => DB::table('esg_metrics')->count(),
            'total_materiality_topics' => DB::table('esg_materiality_topics')->count(),
            'total_disclosure_mappings' => DB::table('esg_disclosure_mappings')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
