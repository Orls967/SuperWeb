<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * DesignSystemAccessibilityService (Fase 240)
 *
 * Implements:
 *  - 240.1 Unified design system across 30 lines (tokens, components, patterns)
 *  - 240.2 Accessibility standards (WCAG 2.1 AA simulation, keyboard navigation, contrast, ARIA labels)
 *  - 240.3 Responsive & mobile-first governance (375px viewport RouteSmoke gate)
 *  - 240.4 UX research loop: usability test findings, task success rate, remediation backlog
 *  - 240.6 Edge case: Module-specific custom component fork review & approval gate
 *  - 240.7 Multi-theme support: Dark mode & High-contrast token definitions
 */
class DesignSystemAccessibilityService
{
    /**
     * Register or update design tokens with dark mode and high-contrast support (240.1 & 240.7).
     */
    public function registerDesignToken(
        string $tokenKey,
        string $category,
        string $lightValue,
        ?string $darkValue = null,
        ?string $highContrastValue = null
    ): object {
        $id = DB::table('platform_design_tokens')->updateOrInsert(
            ['token_key' => $tokenKey],
            [
                'category' => strtoupper($category),
                'light_value' => $lightValue,
                'dark_value' => $darkValue,
                'high_contrast_value' => $highContrastValue,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('platform_design_tokens')->where('token_key', $tokenKey)->first();
    }

    /**
     * Register canonical design system component (240.1 & 240.2).
     */
    public function registerComponent(
        string $componentCode,
        string $componentName,
        float $wcagContrastRatio = 4.50,
        bool $keyboardNavigable = true,
        bool $ariaCompliant = true,
        string $requestingModule = 'CORE'
    ): object {
        $id = DB::table('platform_ui_components')->insertGetId([
            'component_code' => strtoupper($componentCode),
            'component_name' => $componentName,
            'is_canonical' => true,
            'requesting_module' => strtoupper($requestingModule),
            'custom_fork_approved' => true, // canonical is pre-approved
            'wcag_contrast_ratio' => $wcagContrastRatio,
            'keyboard_navigable' => $keyboardNavigable,
            'aria_label_compliant' => $ariaCompliant,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_ui_components')->find($id);
    }

    /**
     * Request a custom component fork with mandatory design system approval (240.6 Edge Case).
     */
    public function requestCustomComponentFork(
        string $componentCode,
        string $componentName,
        string $requestingModule,
        float $wcagContrastRatio = 4.50,
        bool $approvedImmediately = false
    ): object {
        $id = DB::table('platform_ui_components')->insertGetId([
            'component_code' => strtoupper($componentCode),
            'component_name' => $componentName,
            'is_canonical' => false,
            'requesting_module' => strtoupper($requestingModule),
            'custom_fork_approved' => $approvedImmediately,
            'wcag_contrast_ratio' => $wcagContrastRatio,
            'keyboard_navigable' => true,
            'aria_label_compliant' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_ui_components')->find($id);
    }

    /**
     * Approve a custom component fork by Design System team (240.6).
     */
    public function approveCustomFork(int $componentId, string $reviewer): object
    {
        $comp = DB::table('platform_ui_components')->find($componentId);
        if (! $comp) {
            throw new InvalidArgumentException("Component #{$componentId} not found.");
        }

        DB::table('platform_ui_components')
            ->where('id', $componentId)
            ->update([
                'custom_fork_approved' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('platform_ui_components')->find($componentId);
    }

    /**
     * Evaluate A11y and Mobile-First Responsive Gate (240.2, 240.3, 240.5).
     */
    public function evaluateA11yResponsiveGate(
        string $routePath,
        int $viewportWidthPx,
        int $wcagViolationsCount,
        bool $responsiveOverflowDetected
    ): object {
        $isPassed = ($wcagViolationsCount === 0) && (! $responsiveOverflowDetected);
        $scanCode = 'SCAN-'.strtoupper(Str::random(8));

        $id = DB::table('platform_a11y_responsive_scans')->insertGetId([
            'scan_code' => $scanCode,
            'route_path' => $routePath,
            'viewport_width_px' => $viewportWidthPx,
            'wcag_violations_count' => $wcagViolationsCount,
            'responsive_overflow_detected' => $responsiveOverflowDetected,
            'is_gate_passed' => $isPassed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_a11y_responsive_scans')->find($id);
    }

    /**
     * Record UX Research usability testing finding and backlog (240.4).
     */
    public function recordUxResearchFinding(
        string $moduleCode,
        float $taskSuccessRatePct,
        string $usabilityIssueDescription
    ): object {
        $code = 'UXF-'.strtoupper(Str::random(8));

        $id = DB::table('platform_ux_research_backlog')->insertGetId([
            'finding_code' => $code,
            'module_code' => strtoupper($moduleCode),
            'task_success_rate_pct' => $taskSuccessRatePct,
            'usability_issue_description' => $usabilityIssueDescription,
            'remediation_status' => 'OPEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_ux_research_backlog')->find($id);
    }

    /**
     * Design System & Accessibility Audit (`platform:audit`) (240.5, 240.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Custom component forks that are NOT approved
        $unapprovedForks = DB::table('platform_ui_components')
            ->where('is_canonical', false)
            ->where('custom_fork_approved', false)
            ->count();

        // Discrepancy 2: A11y scans passed despite having violations or horizontal overflow
        $falsePassingScans = DB::table('platform_a11y_responsive_scans')
            ->where('is_gate_passed', true)
            ->where(function ($q) {
                $q->where('wcag_violations_count', '>', 0)
                    ->orWhere('responsive_overflow_detected', true);
            })
            ->count();

        // Discrepancy 3: UI components failing minimum WCAG contrast (< 4.5)
        $subContrastComponents = DB::table('platform_ui_components')
            ->where('wcag_contrast_ratio', '<', 4.50)
            ->count();

        $discrepancies = $unapprovedForks + $falsePassingScans + $subContrastComponents;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_tokens' => DB::table('platform_design_tokens')->count(),
            'total_components' => DB::table('platform_ui_components')->count(),
            'total_a11y_scans' => DB::table('platform_a11y_responsive_scans')->count(),
            'total_ux_findings' => DB::table('platform_ux_research_backlog')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
