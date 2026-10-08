<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\DesignSystemAccessibilityService;
use Tests\TestCase;

class DesignSystemAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected DesignSystemAccessibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DesignSystemAccessibilityService::class);
    }

    public function test_register_design_tokens_with_dark_and_contrast_modes(): void
    {
        $token = $this->service->registerDesignToken(
            tokenKey: 'color.brand.primary',
            category: 'COLOR',
            lightValue: '#1E40AF',
            darkValue: '#3B82F6',
            highContrastValue: '#0000FF'
        );

        $this->assertEquals('COLOR', $token->category);
        $this->assertEquals('#1E40AF', $token->light_value);
        $this->assertEquals('#3B82F6', $token->dark_value);
        $this->assertEquals('#0000FF', $token->high_contrast_value);

        $this->assertDatabaseHas('platform_design_tokens', [
            'token_key' => 'color.brand.primary',
            'dark_value' => '#3B82F6',
        ]);
    }

    public function test_canonical_component_registration_and_compliance(): void
    {
        $btn = $this->service->registerComponent(
            componentCode: 'BTN-PRIMARY-MD',
            componentName: 'Primary Button Medium',
            wcagContrastRatio: 7.20,
            keyboardNavigable: true,
            ariaCompliant: true,
            requestingModule: 'CORE'
        );

        $this->assertTrue((bool) $btn->is_canonical);
        $this->assertTrue((bool) $btn->custom_fork_approved);
        $this->assertTrue((bool) $btn->keyboard_navigable);
        $this->assertTrue((bool) $btn->aria_label_compliant);
        $this->assertEquals(7.20, (float) $btn->wcag_contrast_ratio);
    }

    public function test_custom_component_fork_approval_guard(): void
    {
        // 1. Request custom fork for Mining module
        $fork = $this->service->requestCustomComponentFork(
            componentCode: 'MINING-DRILL-TELEMETRY-CARD',
            componentName: 'Mining Drill Telemetry Card',
            requestingModule: 'MINING',
            wcagContrastRatio: 5.50,
            approvedImmediately: false
        );

        $this->assertFalse((bool) $fork->is_canonical);
        $this->assertFalse((bool) $fork->custom_fork_approved);

        // 2. Approve fork by design system team (240.6)
        $approvedFork = $this->service->approveCustomFork((int) $fork->id, 'DESIGN_SYSTEM_LEAD');
        $this->assertTrue((bool) $approvedFork->custom_fork_approved);
    }

    public function test_a11y_and_mobile_responsive_gate(): void
    {
        // 1. Passing scan (375px mobile, 0 violations, no overflow) (240.3 & 240.5)
        $passingScan = $this->service->evaluateA11yResponsiveGate(
            routePath: '/checkout',
            viewportWidthPx: 375,
            wcagViolationsCount: 0,
            responsiveOverflowDetected: false
        );
        $this->assertTrue((bool) $passingScan->is_gate_passed);

        // 2. Failing scan due to horizontal overflow
        $failingScanOverflow = $this->service->evaluateA11yResponsiveGate(
            routePath: '/dashboard/table-view',
            viewportWidthPx: 375,
            wcagViolationsCount: 0,
            responsiveOverflowDetected: true
        );
        $this->assertFalse((bool) $failingScanOverflow->is_gate_passed);

        // 3. Failing scan due to WCAG violations
        $failingScanViolations = $this->service->evaluateA11yResponsiveGate(
            routePath: '/login',
            viewportWidthPx: 375,
            wcagViolationsCount: 2,
            responsiveOverflowDetected: false
        );
        $this->assertFalse((bool) $failingScanViolations->is_gate_passed);
    }

    public function test_ux_research_finding_loop(): void
    {
        $finding = $this->service->recordUxResearchFinding(
            moduleCode: 'HEALTHCARE',
            taskSuccessRatePct: 78.50,
            usabilityIssueDescription: 'Patients struggle finding the prescription refill CTA on mobile screens.'
        );

        $this->assertEquals('HEALTHCARE', $finding->module_code);
        $this->assertEquals(78.50, (float) $finding->task_success_rate_pct);
        $this->assertEquals('OPEN', $finding->remediation_status);
        $this->assertStringStartsWith('UXF-', $finding->finding_code);
    }

    public function test_design_system_audit_healthy_and_discrepancy(): void
    {
        // Setup healthy records
        $this->service->registerDesignToken('spacing.base', 'SPACING', '16px');
        $this->service->registerComponent('INPUT-TEXT', 'Input Text', 4.8);
        $this->service->evaluateA11yResponsiveGate('/home', 375, 0, false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unapproved custom fork
        DB::table('platform_ui_components')->insert([
            'component_code' => 'ROGUE-WIDGET',
            'component_name' => 'Rogue Widget',
            'is_canonical' => false,
            'requesting_module' => 'SHADOW_IT',
            'custom_fork_approved' => false,
            'wcag_contrast_ratio' => 4.6,
            'keyboard_navigable' => true,
            'aria_label_compliant' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
