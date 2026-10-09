<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\UserResearchDesignSystemService;
use Tests\TestCase;

class UserResearchDesignSystemTest extends TestCase
{
    use RefreshDatabase;

    protected UserResearchDesignSystemService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserResearchDesignSystemService::class);
    }

    public function test_research_study_linking_and_route_evaluation_flow(): void
    {
        // 435.1 Record research study for checkout revamp
        $study = $this->service->recordResearchStudy(
            studyCode: 'URS-CHECKOUT-2026',
            title: 'One-Page Checkout Cognitive Load & Friction Study',
            findings: 'Progressive disclosure form elements reduce abandon rate by 18%',
            backlogFeature: 'FEAT-ONE-PAGE-CHECKOUT'
        );

        $this->assertEquals('URS-CHECKOUT-2026', $study->study_code);

        // Verify research evidence exists
        $verified = $this->service->verifyUxChangeResearchEvidence('FEAT-ONE-PAGE-CHECKOUT');
        $this->assertTrue($verified);

        // 435.2 & 435.3 Evaluate route design adoption & a11y conformance
        $route = $this->service->evaluateRouteDesignAndA11y(
            routePath: '/checkout/express',
            adoptionPercent: 95.00,
            customDebtCount: 1,
            wcagScore: 96.50
        );

        $this->assertTrue((bool) $route->a11y_gate_passed);

        // 435.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_missing_research_and_substandard_a11y_blocked_edge_cases(): void
    {
        // 435.6 Risk: Major UX changes without research evidence are blocked
        try {
            $this->service->verifyUxChangeResearchEvidence('FEAT-UNRESEARCHED-REDESIGN');
            $this->fail('Expected exception for missing research evidence');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('lacks mandatory linked research study evidence', $e->getMessage());
        }

        // 435.3 & 435.4 Route with low WCAG score fails quality gate
        try {
            $this->service->evaluateRouteDesignAndA11y(
                routePath: '/legacy/admin',
                adoptionPercent: 60.00, // < 80%
                customDebtCount: 8,
                wcagScore: 72.00 // < 90
            );
            $this->fail('Expected exception for substandard a11y score');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('failed a11y/design system gate', $e->getMessage());
        }
    }
}
