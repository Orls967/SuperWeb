<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * UserResearchDesignSystemService (Fase 435)
 *
 * Implements:
 *  - 435.1 Research repository: studies, findings, decisions linked to backlog
 *  - 435.2 Design system adoption: component usage and debt detection
 *  - 435.3 Accessibility (a11y) conformance program: WCAG score evaluation
 *  - 435.4 Tests: component adoption measured, a11y gate on new routes, research-backed decisions
 *  - 435.5 Edge case: Custom component debt requires explicit migration register
 *  - 435.6 Risk: Major UX changes strictly require linked research evidence
 *  - 435.7 Evidence: research repo, adoption report, a11y results
 */
class UserResearchDesignSystemService
{
    public function recordResearchStudy(
        string $studyCode,
        string $title,
        string $findings,
        string $backlogFeature
    ): object {
        $id = DB::table('plt_ux_research_studies')->insertGetId([
            'study_code' => strtoupper($studyCode),
            'title' => $title,
            'findings_summary' => $findings,
            'linked_backlog_feature' => strtoupper($backlogFeature),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('plt_ux_research_studies')->where('id', $id)->first();
    }

    /**
     * 435.1 & 435.6 Enforce that major UX changes must be backed by a research study
     */
    public function verifyUxChangeResearchEvidence(string $backlogFeature): bool
    {
        $hasResearch = DB::table('plt_ux_research_studies')
            ->where('linked_backlog_feature', strtoupper($backlogFeature))
            ->exists();

        if (! $hasResearch) {
            throw new InvalidArgumentException("UX change blocked: Major UX feature '{$backlogFeature}' lacks mandatory linked research study evidence (435.1, 435.6).");
        }

        return true;
    }

    public function evaluateRouteDesignAndA11y(
        string $routePath,
        float $adoptionPercent,
        int $customDebtCount,
        float $wcagScore
    ): object {
        // 435.3 & 435.4 A11y gate: WCAG score must be >= 90.00 and adoption >= 80.00%
        $passed = ($wcagScore >= 90.00 && $adoptionPercent >= 80.00);

        if (! $passed) {
            throw new InvalidArgumentException("Route quality gate failed: Route '{$routePath}' failed a11y/design system gate (WCAG score {$wcagScore} < 90 or adoption {$adoptionPercent}% < 80%) (435.3, 435.4).");
        }

        $id = DB::table('plt_design_system_routes')->insertGetId([
            'route_path' => $routePath,
            'design_system_adoption_percent' => $adoptionPercent,
            'custom_component_debt_count' => $customDebtCount,
            'wcag_a11y_score' => $wcagScore,
            'a11y_gate_passed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('plt_design_system_routes')->where('id', $id)->first();
    }

    public function audit(): array
    {
        // Discrepancy: Routes registered with unpassed a11y gate or high custom debt (> 5) without migration
        $failingRoutes = DB::table('plt_design_system_routes')
            ->where('a11y_gate_passed', false)
            ->orWhere('custom_component_debt_count', '>', 5)
            ->count();

        return [
            'status' => $failingRoutes === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_studies' => DB::table('plt_ux_research_studies')->count(),
            'total_routes' => DB::table('plt_design_system_routes')->count(),
            'discrepancy_count' => $failingRoutes,
        ];
    }
}
