<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseEthicsSocialLicenseService;
use Tests\TestCase;

class EnterpriseEthicsSocialLicenseTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseEthicsSocialLicenseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseEthicsSocialLicenseService::class);
    }

    public function test_ethics_maturity_and_social_license_index_flow(): void
    {
        // 481.2 High ethics maturity assessment
        $ethics = $this->service->assessEthicsMaturity(
            code: 'ETHICS-2026-H1',
            cultureSurvey: 88.00,
            speakUpHealth: 85.00,
            caseQuality: 92.00
        );

        $this->assertEquals('ETHICS-2026-H1', $ethics->cycle_code);
        $this->assertFalse((bool) $ethics->requires_mandatory_improvement_plan);
        $this->assertGreaterThan(80.00, (float) $ethics->composite_ethics_maturity_score);

        // 481.3 & 481.6 Social License Index computation with documented open methodology
        $index = $this->service->computeSocialLicenseIndex(
            code: 'SLI-2026-ANNUAL',
            communityTrust: 86.00,
            regulatoryStanding: 94.00,
            partnerConfidence: 90.00,
            employeePride: 88.00,
            openMethodology: true
        );

        $this->assertEquals('SLI-2026-ANNUAL', $index->index_code);
        $this->assertTrue((bool) $index->open_empirical_methodology_documented);
        $this->assertGreaterThan(85.00, (float) $index->composite_social_license_index);

        // 481.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_low_maturity_plan_requirement_and_undocumented_methodology_edge_cases(): void
    {
        // 481.5 Edge case: Low ethics maturity (< 70) automatically enforces mandatory remediation plan
        $low = $this->service->assessEthicsMaturity('ETHICS-LOW', 55.00, 60.00, 65.00);
        $this->assertTrue((bool) $low->requires_mandatory_improvement_plan);
        $this->assertFalse((bool) $low->improvement_plan_submitted);

        // Submitting plan satisfies requirement
        $submitted = $this->service->submitImprovementPlan('ETHICS-LOW');
        $this->assertTrue((bool) $submitted->improvement_plan_submitted);

        // 481.6 Risk: Social license calculation with undocumented methodology is blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Social license index calculation requires open, auditable empirical methodology documentation');

        $this->service->computeSocialLicenseIndex('SLI-OPAQUE', 80, 80, 80, 80, false);
    }
}
