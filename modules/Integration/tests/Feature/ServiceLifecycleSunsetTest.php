<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ServiceLifecycleSunsetService;
use Tests\TestCase;

class ServiceLifecycleSunsetTest extends TestCase
{
    use RefreshDatabase;

    protected ServiceLifecycleSunsetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ServiceLifecycleSunsetService::class);
    }

    public function test_active_consumer_blocks_sunset_without_waiver_edge_case(): void
    {
        // Register deprecated API service with 3 active consumers
        $this->service->registerApiService(
            apiServiceCode: 'API-PAYMENT-LEGACY-V1',
            lifecycleState: 'DEPRECATED',
            activeConsumers: 3
        );

        // 1. Attempting sunset without dated waiver throws exception (366.4 & 366.5 Edge Case)
        try {
            $this->service->executeSunset(
                apiServiceCode: 'API-PAYMENT-LEGACY-V1',
                hasDatedWaiver: false, // No waiver!
                archivedEvidenceRetained: true
            );
            $this->fail('Expected exception for sunsetting API with active consumers and no waiver');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Active consumers (3) require a formal dated migration waiver before sunset', $e->getMessage());
        }

        // 2. Sunset with formal dated waiver succeeds and revokes credentials (366.3, 366.4, 366.5)
        $sunsetWithWaiver = $this->service->executeSunset(
            apiServiceCode: 'API-PAYMENT-LEGACY-V1',
            hasDatedWaiver: true,
            archivedEvidenceRetained: true
        );
        $this->assertEquals('SUNSET', $sunsetWithWaiver->current_lifecycle_state);
        $this->assertTrue((bool) $sunsetWithWaiver->credentials_revoked);
        $this->assertTrue((bool) $sunsetWithWaiver->sunset_completed);
    }

    public function test_archival_evidence_mandatory_before_sunset_risk(): void
    {
        // Register API service with 0 consumers
        $this->service->registerApiService(
            apiServiceCode: 'API-WEATHER-STATION-V1',
            lifecycleState: 'DEPRECATED',
            activeConsumers: 0
        );

        // Sunset without archival evidence fails (366.4 & 366.6 Risk)
        try {
            $this->service->executeSunset(
                apiServiceCode: 'API-WEATHER-STATION-V1',
                hasDatedWaiver: false,
                archivedEvidenceRetained: false // Missing archive!
            );
            $this->fail('Expected exception for missing archival evidence');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Archiving evidence mandatory before retirement', $e->getMessage());
        }
    }

    public function test_api_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerApiService('API-AUD', 'SUPPORTED', 0);
        $this->service->executeSunset('API-AUD', false, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: sunset completed with active consumers without waiver
        DB::table('platform_api_service_sunsets')->insert([
            'api_service_code' => 'API-DEFECT-UNWAIVED',
            'current_lifecycle_state' => 'SUNSET',
            'active_consumer_count' => 5, // Active consumers!
            'has_valid_dated_waiver' => false, // Discrepancy!
            'archived_evidence_retained' => true,
            'credentials_revoked' => true,
            'sunset_completed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
