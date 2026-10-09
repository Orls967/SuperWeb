<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PrivacyEngineeringDataProductsService;
use Tests\TestCase;

class PrivacyEngineeringDataProductsTest extends TestCase
{
    use RefreshDatabase;

    protected PrivacyEngineeringDataProductsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PrivacyEngineeringDataProductsService::class);
    }

    public function test_processing_proof_fail_closed_guard(): void
    {
        // 1. Processing without valid consent proof is strictly blocked (341.4 & 341.6 Fail-Closed)
        try {
            $this->service->authorizeDataProcessing(
                jobCode: 'JOB-MARKETING-CAMPAIGN-01',
                subjectId: 'CUST-INDIVIDUAL-8821',
                purpose: 'DIRECT_MARKETING',
                hasConsentProof: false // Missing proof!
            );
            $this->fail('Expected exception for processing without consent proof');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Privacy fail-closed enforcement: Data processing blocked', $e->getMessage());
        }

        // Verify the blocked attempt was recorded in ledger
        $blockedRecord = DB::table('privacy_data_processing_proofs')->where('processing_job_code', 'JOB-MARKETING-CAMPAIGN-01')->first();
        $this->assertNotNull($blockedRecord);
        $this->assertTrue((bool) $blockedRecord->processing_blocked);

        // 2. Processing with verified consent proof succeeds (341.2 & 341.4)
        $authorized = $this->service->authorizeDataProcessing(
            jobCode: 'JOB-MARKETING-CAMPAIGN-02',
            subjectId: 'CUST-INDIVIDUAL-8821',
            purpose: 'DIRECT_MARKETING',
            hasConsentProof: true
        );
        $this->assertTrue((bool) $authorized->has_valid_consent_proof);
        $this->assertFalse((bool) $authorized->processing_blocked);
    }

    public function test_consent_revocation_propagation_sla_edge_case(): void
    {
        // 1. Propagation within SLA (12s <= 30s) passes clean (341.2 & 341.4)
        $fastEvent = $this->service->propagateConsentRevocation(
            revocationCode: 'REV-FAST-01',
            subjectId: 'CUST-999',
            purpose: 'ANALYTICS',
            propagationLatencySec: 12.5,
            slaSec: 30.0
        );
        $this->assertFalse((bool) $fastEvent->sla_breached);

        // 2. Propagation lagging (> 30s) flags SLA breach (341.5 Edge Case)
        $laggingEvent = $this->service->propagateConsentRevocation(
            revocationCode: 'REV-LAG-02',
            subjectId: 'CUST-999',
            purpose: 'DIRECT_MARKETING',
            propagationLatencySec: 45.0, // Exceeded 30s!
            slaSec: 30.0
        );
        $this->assertTrue((bool) $laggingEvent->sla_breached);
    }

    public function test_privacy_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->authorizeDataProcessing('JOB-AUD', 'S1', 'PURPOSE', true);
        $this->service->propagateConsentRevocation('REV-AUD', 'S1', 'PURPOSE', 10.0, 30.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unnotified downstream revocation
        DB::table('privacy_consent_revocation_events')->insert([
            'revocation_code' => 'REV-DEFECT-UNNOTIFIED',
            'subject_id' => 'S2',
            'purpose_scope' => 'PURPOSE',
            'propagation_latency_seconds' => 10.0,
            'sla_threshold_seconds' => 30.0,
            'sla_breached' => false,
            'downstream_notified' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
