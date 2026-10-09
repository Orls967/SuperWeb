<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\EnterpriseServiceManagementCmdbService;
use Tests\TestCase;

class EnterpriseServiceManagementCmdbTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseServiceManagementCmdbService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseServiceManagementCmdbService::class);
    }

    public function test_unauthorized_configuration_drift_rollback_edge_case(): void
    {
        // 1. Authorized configuration update succeeds without rollback (362.1 & 362.4)
        $authorized = $this->service->evaluateConfigurationDrift(
            serviceCiCode: 'CI-POSTGRES-HA-01',
            serviceName: 'PostgreSQL Primary Cluster',
            baselineHash: 'HASH-V1-ORIGINAL',
            currentHash: 'HASH-V2-APPROVED',
            isAuthorized: true
        );
        $this->assertTrue((bool) $authorized->drift_detected);
        $this->assertFalse((bool) $authorized->unauthorized_change);
        $this->assertFalse((bool) $authorized->drift_alert_sent);
        $this->assertFalse((bool) $authorized->automatic_remediation_rolled_back);
        $this->assertEquals('HASH-V2-APPROVED', $authorized->active_configuration_hash);

        // 2. Unauthorized drift triggers alert and rolls back configuration to baseline (362.3 & 362.5 Edge Case)
        $unauthorized = $this->service->evaluateConfigurationDrift(
            serviceCiCode: 'CI-POSTGRES-HA-02',
            serviceName: 'PostgreSQL Standby Cluster',
            baselineHash: 'HASH-V1-ORIGINAL',
            currentHash: 'HASH-V2-ROGUE-CHANGE', // Unauthorized rogue tweak!
            isAuthorized: false
        );
        $this->assertTrue((bool) $unauthorized->drift_detected);
        $this->assertTrue((bool) $unauthorized->unauthorized_change);
        $this->assertTrue((bool) $unauthorized->drift_alert_sent);
        $this->assertTrue((bool) $unauthorized->automatic_remediation_rolled_back);
        $this->assertEquals('HASH-V1-ORIGINAL', $unauthorized->active_configuration_hash);
    }

    public function test_incident_technical_customer_correlation(): void
    {
        // Correlate technical incident with customer issue (362.2 & 362.4)
        $correlation = $this->service->correlateIncident(
            recordCode: 'CORR-REC-001',
            serviceCiCode: 'CI-POSTGRES-HA-01',
            techIncidentId: 'INC-TECH-DB-FAILOVER-991',
            custIssueId: 'TICKET-CUSTOMER-PAYMENT-DELAY-412',
            correlationId: 'CORR-UUID-7711-2299'
        );

        $this->assertEquals('CORR-UUID-7711-2299', $correlation->correlation_id);
        $this->assertEquals('INC-TECH-DB-FAILOVER-991', $correlation->technical_incident_id);
        $this->assertEquals('TICKET-CUSTOMER-PAYMENT-DELAY-412', $correlation->customer_issue_id);
    }

    public function test_platform_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->evaluateConfigurationDrift('CI-AUD', 'Service', 'H1', 'H1', true);
        $this->service->correlateIncident('REC-AUD', 'CI-AUD', 'T1', 'C1', 'CORR-1');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unauthorized drift without rollback
        DB::table('platform_cmdb_service_registries')->insert([
            'service_ci_code' => 'CI-DEFECT-UNROLLED',
            'service_name' => 'Defect',
            'approved_baseline_hash' => 'H1',
            'active_configuration_hash' => 'H2',
            'drift_detected' => true,
            'unauthorized_change' => true,
            'drift_alert_sent' => false, // Discrepancy!
            'automatic_remediation_rolled_back' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
