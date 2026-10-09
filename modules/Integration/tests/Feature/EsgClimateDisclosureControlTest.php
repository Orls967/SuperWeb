<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EsgClimateDisclosureControlService;
use Tests\TestCase;

class EsgClimateDisclosureControlTest extends TestCase
{
    use RefreshDatabase;

    protected EsgClimateDisclosureControlService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EsgClimateDisclosureControlService::class);
    }

    public function test_esg_disclosure_creation_and_signoff(): void
    {
        // 407.1 Register disclosure
        $disclosure = $this->service->registerDisclosure(
            disclosureCode: 'ESG-GHG-2026',
            metricName: 'Scope 1 & 2 GHG Emissions',
            period: '2026-FY',
            metricValue: 12540.5000,
            unit: 'tCO2e',
            systemSource: 'ESG_CARBON_ACCOUNTING_DATABASE',
            owner: 'Chief Sustainability Officer',
            evidenceBundleHash: 'sha256_bundle_9921_verified'
        );

        $this->assertEquals('ESG-GHG-2026', $disclosure->disclosure_code);
        $this->assertFalse((bool) $disclosure->is_published);

        // Sign off and publish (407.2)
        $published = $this->service->signOffAndPublish('ESG-GHG-2026');
        $this->assertTrue((bool) $published->is_published);
        $this->assertTrue((bool) $published->signed_off_by_esg_officer);

        // 407.3 Restatement test
        $restated = $this->service->restateDisclosure(
            disclosureCode: 'ESG-GHG-2026',
            newValue: 12480.0000,
            reason: 'Refined refinery diesel emission factor',
            notifiedStakeholders: 'BOD, GRI Auditor, Public Registry'
        );

        $this->assertEquals(2, $restated->version);
        $this->assertEquals(12480.0000, (float) $restated->metric_value);

        // 407.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['unsupported_published']);
    }

    public function test_unsupported_figure_blocked_from_publishing_edge_case(): void
    {
        // 407.5 Edge case: ESG figure without system source is strictly blocked
        $this->service->registerDisclosure(
            disclosureCode: 'ESG-WATER-ESTIMATE',
            metricName: 'Estimated Water Footprint',
            period: '2026-FY',
            metricValue: 5000.0000,
            unit: 'm3',
            systemSource: null, // Unsupported!
            owner: 'Analyst X',
            evidenceBundleHash: 'fake_hash'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Publication blocked: ESG figure lacks verified system source');

        $this->service->signOffAndPublish('ESG-WATER-ESTIMATE');
    }
}
