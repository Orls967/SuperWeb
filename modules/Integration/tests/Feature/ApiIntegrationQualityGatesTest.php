<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ApiIntegrationQualityGatesService;
use Tests\TestCase;

class ApiIntegrationQualityGatesTest extends TestCase
{
    use RefreshDatabase;

    protected ApiIntegrationQualityGatesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ApiIntegrationQualityGatesService::class);
    }

    public function test_partner_sandbox_certification_and_production_enablement(): void
    {
        // 429.2 Register partner
        $p = $this->service->registerPartner('PRT-GARUDA-01', 'PT Garuda Indonesia (Persero) Tbk');
        $this->assertEquals('PRT-GARUDA-01', $p->partner_code);
        $this->assertFalse((bool) $p->production_enabled);

        // 429.1, 429.2, 429.6 Record successful sandbox test suite, schema compatibility, and semantic contract approval
        $this->service->recordCertificationResults(
            partnerCode: 'PRT-GARUDA-01',
            sandboxPassed: true,
            schemaPassed: true,
            semanticApproved: true
        );

        // 429.2 Issue production enablement with 1-year certificate expiry
        $enabled = $this->service->issueProductionEnablement('PRT-GARUDA-01', now()->addYear()->toDateString());
        $this->assertTrue((bool) $enabled->production_enabled);
        $this->assertNotNull($enabled->production_api_key);
        $this->assertNotNull($enabled->certificate_token);

        // 429.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_unpassed_gates_block_production_edge_case(): void
    {
        // 429.5 Edge case: Partner failing sandbox or semantic review cannot receive production key
        $this->service->registerPartner('PRT-FAIL-01', 'Failing Partner Co');

        $this->service->recordCertificationResults(
            partnerCode: 'PRT-FAIL-01',
            sandboxPassed: false, // FAILED!
            schemaPassed: true,
            semanticApproved: false
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Partner has not passed sandbox test suite');

        $this->service->issueProductionEnablement('PRT-FAIL-01', now()->addYear()->toDateString());
    }
}
