<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ServiceCatalogDeveloperPortalService;
use Tests\TestCase;

class ServiceCatalogDeveloperPortalTest extends TestCase
{
    use RefreshDatabase;

    protected ServiceCatalogDeveloperPortalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ServiceCatalogDeveloperPortalService::class);
    }

    public function test_unregistered_catalog_entry_publish_rejection_edge_case(): void
    {
        // 1. Publishing capability without catalog entry is rejected (361.5 Edge Case)
        try {
            $this->service->publishCapability(
                capabilityCode: 'CAP-SMELTER-TELEMETRY-API',
                serviceName: 'Smelter Telemetry Service',
                ownerTeam: 'IOT_CORE',
                lifecycleStatus: 'ACTIVE',
                catalogEntryRegistered: false // Unregistered!
            );
            $this->fail('Expected exception for publishing uncataloged capability');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot be published without formal catalog registration', $e->getMessage());
        }

        // 2. Formally registered capability publishes successfully (361.1 & 361.4)
        $cap = $this->service->publishCapability(
            capabilityCode: 'CAP-SMELTER-TELEMETRY-API-V2',
            serviceName: 'Smelter Telemetry Service',
            ownerTeam: 'IOT_CORE',
            lifecycleStatus: 'ACTIVE',
            catalogEntryRegistered: true
        );
        $this->assertTrue((bool) $cap->catalog_entry_registered);
        $this->assertTrue((bool) $cap->publish_permitted);
    }

    public function test_developer_sandbox_isolation_enforcement(): void
    {
        // 1. Requesting non-isolated sandbox throws exception (361.2 & 361.4)
        try {
            $this->service->requestSandbox(
                sandboxCode: 'SBX-NON-ISOLATED-01',
                capabilityCode: 'CAP-SMELTER-TELEMETRY-API-V2',
                requesterTeam: 'ANALYTICS_DATA_SCIENCE',
                scopeIsolated: false, // Non-isolated!
                approvalGranted: true
            );
            $this->fail('Expected exception for non-isolated sandbox request');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Developer sandbox must have strict tenant isolation', $e->getMessage());
        }

        // 2. Isolated sandbox succeeds (361.2 & 361.4)
        $sbx = $this->service->requestSandbox(
            sandboxCode: 'SBX-ISOLATED-02',
            capabilityCode: 'CAP-SMELTER-TELEMETRY-API-V2',
            requesterTeam: 'ANALYTICS_DATA_SCIENCE',
            scopeIsolated: true,
            approvalGranted: true
        );
        $this->assertTrue((bool) $sbx->sandbox_scope_isolated);
        $this->assertTrue((bool) $sbx->approval_granted);
    }

    public function test_platform_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->publishCapability('C-AUD', 'Service', 'TEAM', 'ACTIVE', true);
        $this->service->requestSandbox('S-AUD', 'C-AUD', 'TEAM2', true, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: approved sandbox with non-isolated scope
        DB::table('service_developer_portal_sandboxes')->insert([
            'sandbox_code' => 'S-DEFECT-LEAKY',
            'capability_code' => 'C-AUD',
            'requester_team' => 'TEAM3',
            'sandbox_scope_isolated' => false, // Discrepancy!
            'approval_granted' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
