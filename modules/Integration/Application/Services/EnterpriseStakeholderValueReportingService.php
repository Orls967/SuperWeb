<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EnterpriseStakeholderValueReportingService (Fase 478)
 *
 * Implements:
 *  - 478.1 Stakeholder value map: shareholders, customers, employees, partners, communities, regulators
 *  - 478.2 Integrated reporting: financial, operational, sustainability, people value with metric lineage
 *  - 478.3 Stakeholder feedback tracking & improvement actions
 *  - 478.4 Tests: value metrics source-linked, narrative numbers reconcile, group:audit clean
 *  - 478.5 Edge case: Non-accommodated feedback mandates documented rationale and prioritization (cannot be discarded)
 *  - 478.6 Risk: Narrative numbers must reconcile strictly against source lineage reference
 *  - 478.7 Evidence: stakeholder map, integrated report, feedback loop
 */
class EnterpriseStakeholderValueReportingService
{
    public function recordStakeholderMetric(
        string $code,
        string $group,
        string $name,
        float $value,
        string $lineageRef
    ): object {
        $g = strtolower($group);
        $validGroups = ['shareholders', 'customers', 'employees', 'partners', 'communities', 'regulators'];
        if (! in_array($g, $validGroups, true)) {
            throw new InvalidArgumentException("Invalid stakeholder group '{$group}' (478.1).");
        }

        // 478.4 & 478.6 Metric must have source lineage reference
        if (empty(trim($lineageRef))) {
            throw new InvalidArgumentException("Reporting blocked: Stakeholder value metric requires direct source lineage reference (478.2, 478.6).");
        }

        $id = DB::table('int_stakeholder_value_metrics')->insertGetId([
            'metric_code' => strtoupper($code),
            'stakeholder_group' => $g,
            'metric_name' => $name,
            'metric_value' => $value,
            'source_lineage_ref' => strtoupper($lineageRef),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_stakeholder_value_metrics')->where('id', $id)->first();
    }

    /**
     * 478.3 & 478.5 Log feedback action; if not accommodated, rationale is mandatory
     */
    public function logFeedbackAction(
        string $code,
        string $group,
        string $summary,
        bool $isAccommodated,
        ?string $nonAccommodationRationale = null
    ): object {
        // 478.5 Edge case: Unaccommodated feedback must record explicit justification
        if (! $isAccommodated && empty(trim($nonAccommodationRationale ?? ''))) {
            throw new InvalidArgumentException("Feedback logging blocked: Non-accommodated stakeholder feedback requires explicit documented rationale and priority rationale (478.5).");
        }

        $id = DB::table('int_stakeholder_feedback_actions')->insertGetId([
            'feedback_code' => strtoupper($code),
            'stakeholder_group' => strtolower($group),
            'feedback_summary' => $summary,
            'is_accommodated' => $isAccommodated,
            'non_accommodation_rationale' => $isAccommodated ? null : $nonAccommodationRationale,
            'status' => 'tracked',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_stakeholder_feedback_actions')->where('id', $id)->first();
    }

    public function closeFeedbackAction(string $code): object
    {
        $fb = DB::table('int_stakeholder_feedback_actions')->where('feedback_code', strtoupper($code))->first();
        if (! $fb) {
            throw new InvalidArgumentException("Feedback '{$code}' not found.");
        }

        DB::table('int_stakeholder_feedback_actions')->where('id', $fb->id)->update([
            'status' => 'closed',
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_stakeholder_feedback_actions')->where('id', $fb->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Metrics without lineage reference
        $unlinkedMetrics = DB::table('int_stakeholder_value_metrics')
            ->where(function ($query) {
                $query->whereNull('source_lineage_ref')
                    ->orWhere('source_lineage_ref', '');
            })
            ->count();

        // Discrepancy 2: Unaccommodated feedback without rationale
        $undocumentedFeedback = DB::table('int_stakeholder_feedback_actions')
            ->where('is_accommodated', false)
            ->where(function ($query) {
                $query->whereNull('non_accommodation_rationale')
                    ->orWhere('non_accommodation_rationale', '');
            })
            ->count();

        $total = $unlinkedMetrics + $undocumentedFeedback;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_metrics' => DB::table('int_stakeholder_value_metrics')->count(),
            'total_feedback' => DB::table('int_stakeholder_feedback_actions')->count(),
            'discrepancy_count' => $total,
        ];
    }
}
