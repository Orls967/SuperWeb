<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DomainCapacityEnvelopesService;
use Tests\TestCase;

class DomainCapacityEnvelopesTest extends TestCase
{
    use RefreshDatabase;

    protected DomainCapacityEnvelopesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DomainCapacityEnvelopesService::class);
    }

    public function test_sustainable_basis_envelope_risk(): void
    {
        // 1. Defining envelope without sustainable 24h benchmark fails (392.1 & 392.6 Risk)
        try {
            $this->service->defineEnvelope(
                domainName: 'HEALTHCARE',
                maxSupportedTps: 5000,
                sustainable24hBasis: false // Momentary spike rejected!
            );
            $this->fail('Expected exception for unsustainable envelope');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Domain envelopes must be benchmarked against sustainable 24-hour', $e->getMessage());
        }

        // 2. Sustainable envelope definition succeeds (392.1)
        $env = $this->service->defineEnvelope(
            domainName: 'HEALTHCARE',
            maxSupportedTps: 5000,
            sustainable24hBasis: true
        );
        $this->assertEquals(5000, $env->max_supported_tps);
        $this->assertTrue((bool) $env->sustainable_24h_basis);
    }

    public function test_admission_control_rejection_edge_case(): void
    {
        // Define domain envelope
        $this->service->defineEnvelope('FESTIVAL_TICKETING', 10000, true);

        // 1. Load exceeding envelope without preparation is rejected by admission control (392.4 & 392.5 Edge Case)
        try {
            $this->service->evaluateAdmissionControl(
                requestCode: 'REQ-TICKET-DROP-SPIKE-01',
                domainName: 'FESTIVAL_TICKETING',
                incomingTps: 25000 // 25000 > 10000 limit!
            );
            $this->fail('Expected exception for load exceeding capacity envelope');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Load of 25000 TPS exceeds domain envelope limit of 10000 TPS', $e->getMessage());
        }

        // Verify rejected admission record preserves data invariants
        $event = DB::table('global_stress_admission_control_events')->where('request_code', 'REQ-TICKET-DROP-SPIKE-01')->first();
        $this->assertNotNull($event);
        $this->assertFalse((bool) $event->request_admitted);
        $this->assertStringContainsString('ADMISSION_BREACH', $event->rejection_reason);
        $this->assertTrue((bool) $event->data_invariants_preserved);

        // 2. Load within envelope is admitted smoothly (392.4)
        $admitted = $this->service->evaluateAdmissionControl(
            requestCode: 'REQ-TICKET-NORMAL-01',
            domainName: 'FESTIVAL_TICKETING',
            incomingTps: 8000
        );
        $this->assertTrue((bool) $admitted->request_admitted);
        $this->assertNull($admitted->rejection_reason);
    }

    public function test_capacity_envelope_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->defineEnvelope('AUDIT_DOMAIN', 2000, true);
        $this->service->evaluateAdmissionControl('REQ-AUD', 'AUDIT_DOMAIN', 1000);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unsustainable envelope
        DB::table('global_stress_domain_capacity_envelopes')->insert([
            'domain_name' => 'DEFECT_DOMAIN',
            'max_supported_tps' => 100,
            'sustainable_24h_basis' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
