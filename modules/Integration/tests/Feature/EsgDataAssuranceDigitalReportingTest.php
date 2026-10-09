<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EsgDataAssuranceDigitalReportingService;
use Tests\TestCase;

class EsgDataAssuranceDigitalReportingTest extends TestCase
{
    use RefreshDatabase;

    protected EsgDataAssuranceDigitalReportingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EsgDataAssuranceDigitalReportingService::class);
    }

    public function test_digital_disclosure_ingestion_and_publishing_flow(): void
    {
        // 449.1 Ingest GHG Scope 1 disclosure datapoint matching XBRL tag with valid evidence
        $disc = $this->service->ingestDatapoint(
            tag: 'esg-gri:Scope1DirectEmissions_tCO2e',
            topic: 'climate',
            datapointValue: 45210.5000,
            taggedXbrlValue: 45210.5000,
            evidenceRef: 'DOC-ENV-CEMS-AUDIT-2026-P01',
            unresolvedFindings: false
        );

        $this->assertEquals('esg-gri:Scope1DirectEmissions_tCO2e', $disc->disclosure_tag);
        $this->assertEquals('UNQUALIFIED', $disc->assurance_opinion);

        // 449.4 Publish disclosure
        $published = $this->service->publishDisclosure('esg-gri:Scope1DirectEmissions_tCO2e');
        $this->assertTrue((bool) $published->is_published);

        // 449.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_tagging_mismatch_and_unsupported_figure_blocked_edge_cases(): void
    {
        // 449.4 Missing evidence reference is blocked
        try {
            $this->service->ingestDatapoint('esg-dummy', 'climate', 100, 100, '');
            $this->fail('Expected exception for unsupported figure');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unsupported disclosure figure! Audit evidence link reference is mandatory', $e->getMessage());
        }

        // 449.5 Edge case: Tagging mismatch blocks publication
        $this->service->ingestDatapoint(
            tag: 'esg-mismatch:WaterRecycled',
            topic: 'water',
            datapointValue: 12000.0000,
            taggedXbrlValue: 10000.0000, // Mismatch!
            evidenceRef: 'DOC-WATER-METER-01'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Digital XBRL tag value (10000) mismatches ingested datapoint value (12000)');

        $this->service->publishDisclosure('esg-mismatch:WaterRecycled');
    }
}
