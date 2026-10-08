<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\HealthInsuranceWellbeingService;
use Tests\TestCase;

class HealthInsuranceWellbeingTest extends TestCase
{
    use RefreshDatabase;

    protected HealthInsuranceWellbeingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HealthInsuranceWellbeingService::class);
    }

    public function test_clinical_data_scope_enforcement_and_unrestricted_prevention_risk(): void
    {
        // 1. Attempting unrestricted clinical sharing fails (382.1, 382.4, 382.6 Risk)
        try {
            $this->service->shareCareReferral(
                journeyCode: 'REF-ONCOLOGY-001',
                patientId: 'PATIENT-HR-EMP-551',
                medicalDataScoped: false, // Unscoped!
                unrestrictedSharingAttempted: true // Unrestricted!
            );
            $this->fail('Expected exception for unrestricted medical record sharing');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unrestricted clinical record sharing prohibited', $e->getMessage());
        }

        // 2. Properly scoped referral contract succeeds (382.1 & 382.4)
        $referral = $this->service->shareCareReferral(
            journeyCode: 'REF-ONCOLOGY-002',
            patientId: 'PATIENT-HR-EMP-551',
            medicalDataScoped: true,
            unrestrictedSharingAttempted: false
        );
        $this->assertTrue((bool) $referral->medical_data_scoped);
        $this->assertTrue((bool) $referral->unrestricted_sharing_prevented);
    }

    public function test_disputed_claim_appeal_with_escrow_protection_edge_case(): void
    {
        // Clinically valid claim rejected by insurer enters appeal with escrow protection so clinic doesn't suffer loss (382.4 & 382.5 Edge Case)
        $appeal = $this->service->fileDisputedClaimAppeal(
            claimCode: 'CLM-CARDIAC-SURGERY-01',
            claimAmountUsd: 18500.00,
            isClinicallyValid: true,
            insurerRejected: true // Insurer dispute!
        );

        $this->assertTrue((bool) $appeal->escrow_funded_to_protect_clinic);
        $this->assertEquals('ESCROWED_FOR_APPEAL', $appeal->appeal_status);
    }

    public function test_hosp_and_ins_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->shareCareReferral('J-AUD', 'P-1', true, false);
        $this->service->fileDisputedClaimAppeal('C-AUD', 500.00, true, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: valid claim rejected without clinic escrow protection
        DB::table('global_health_disputed_claim_appeals')->insert([
            'claim_code' => 'C-DEFECT-UNPROTECTED',
            'claim_amount_usd' => 1000.00,
            'is_clinically_valid' => true,
            'insurer_rejected' => true,
            'escrow_funded_to_protect_clinic' => false, // Discrepancy!
            'appeal_status' => 'REJECTED_UNPROTECTED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
