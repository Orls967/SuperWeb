<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PartnerOnboardingEcosystemService;
use Tests\TestCase;

class PartnerOnboardingEcosystemTest extends TestCase
{
    use RefreshDatabase;

    protected PartnerOnboardingEcosystemService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PartnerOnboardingEcosystemService::class);
    }

    public function test_partner_onboarding_gates_and_reapply_edge_case(): void
    {
        // 1. Missing contract or certification fails go-live and marks status REJECTED (371.1, 371.4, 371.5 Edge Case)
        try {
            $this->service->evaluateOnboardingGoLive(
                partnerCode: 'PTN-SUPPLIER-TIRE-01',
                partnerName: 'PT Nusantara Ban Makmur',
                kybVerified: true,
                contractSigned: false, // Missing contract!
                certificationPassed: false // Incomplete cert!
            );
            $this->fail('Expected exception for failed onboarding gates');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('rejected due to Contract signature missing, Technical certification incomplete', $e->getMessage());
        }

        // Verify rejected record allows re-application
        $rejected = DB::table('partner_ecosystem_onboardings')->where('partner_code', 'PTN-SUPPLIER-TIRE-01')->first();
        $this->assertNotNull($rejected);
        $this->assertEquals('REJECTED', $rejected->onboarding_status);
        $this->assertTrue((bool) $rejected->can_reapply_after_remediation);

        // 2. Fully compliant partner passes go-live (371.1 & 371.4)
        $approved = $this->service->evaluateOnboardingGoLive(
            partnerCode: 'PTN-SUPPLIER-TIRE-02',
            partnerName: 'PT Nusantara Ban Prima',
            kybVerified: true,
            contractSigned: true,
            certificationPassed: true
        );
        $this->assertEquals('GO_LIVE_APPROVED', $approved->onboarding_status);
        $this->assertFalse((bool) $approved->can_reapply_after_remediation);
    }

    public function test_offboarding_access_revocation_enforcement_risk(): void
    {
        // 1. Attempting offboarding closure without API access revocation fails (371.3, 371.4, 371.6 Risk)
        try {
            $this->service->executePartnerOffboarding(
                offboardingCode: 'OFF-PTN-001',
                partnerCode: 'PTN-SUPPLIER-TIRE-02',
                apiAccessRevoked: false, // Access not revoked!
                balancesSettled: true
            );
            $this->fail('Expected exception for offboarding without access revocation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('API access must be formally revoked before contract closure', $e->getMessage());
        }

        // 2. Offboarding with revoked access succeeds (371.3 & 371.4)
        $offboarded = $this->service->executePartnerOffboarding(
            offboardingCode: 'OFF-PTN-002',
            partnerCode: 'PTN-SUPPLIER-TIRE-02',
            apiAccessRevoked: true,
            balancesSettled: true
        );
        $this->assertTrue((bool) $offboarded->api_access_revoked);
        $this->assertTrue((bool) $offboarded->contract_closure_permitted);
    }

    public function test_ptn_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->evaluateOnboardingGoLive('P-AUD', 'Partner', true, true, true);
        $this->service->executePartnerOffboarding('O-AUD', 'P-AUD', true, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: closure permitted without access revocation
        DB::table('partner_ecosystem_offboardings')->insert([
            'offboarding_code' => 'O-DEFECT-UNREVOKED',
            'partner_code' => 'P-AUD',
            'api_access_revoked' => false, // Discrepancy!
            'balances_settled' => true,
            'contract_closure_permitted' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
