<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\TestDataComplianceService;
use Tests\TestCase;

class TestDataComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected TestDataComplianceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TestDataComplianceService::class);
    }

    public function test_test_data_environment_pii_scan_and_purge_flow(): void
    {
        // 474.1 Register staging environment with masked data tier
        $env = $this->service->registerTestEnvironment('ENV-STAGING-01', 'masked');
        $this->assertEquals('ENV-STAGING-01', $env->env_code);
        $this->assertEquals('clean', $env->status);

        // 474.3 & 474.5 Automated scan detects PII leak (e.g. unmasked test dump)
        $scanned = $this->service->scanForPiiLeakage('ENV-STAGING-01', true);
        $this->assertTrue((bool) $scanned->pii_leakage_detected);
        $this->assertEquals('pii_leak_purging', $scanned->status);

        // 474.5 Purge contaminated data and regenerate clean masked set
        $purged = $this->service->purgeAndRegenerate('ENV-STAGING-01');
        $this->assertFalse((bool) $purged->pii_leakage_detected);
        $this->assertEquals('clean', $purged->status);

        // 474.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_unapproved_production_cloning_blocked_edge_case(): void
    {
        // 474.1 & 474.4 Unapproved cloning of production database into dev/test is strictly blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unapproved cloning of production database into non-prod environment blocked');

        $this->service->registerTestEnvironment('ENV-DEV-ROGUE', 'production_clone', false);
    }
}
