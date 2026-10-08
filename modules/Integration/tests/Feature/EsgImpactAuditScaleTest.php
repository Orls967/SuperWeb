<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EsgImpactAuditScaleService;
use Tests\TestCase;

class EsgImpactAuditScaleTest extends TestCase
{
    use RefreshDatabase;

    protected EsgImpactAuditScaleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EsgImpactAuditScaleService::class);
    }

    public function test_independent_verifier_assignment_and_conflict_of_interest_guard(): void
    {
        // 1. Clean verifier assigned succeeds (286.2 & 286.5)
        $verif = $this->service->assignVerifier('PRJ-PEATLAND-RESTORE-01', 'VERIFIER-SGS-INDONESIA', false);
        $this->assertFalse((bool) $verif->has_conflict_of_interest);

        // 2. Conflict of interest strictly blocked (286.2 & 286.6 Edge Case)
        try {
            $this->service->assignVerifier('PRJ-PEATLAND-RESTORE-01', 'VERIFIER-AFFILIATED-INTERNAL', true);
            $this->fail('Expected exception for verifier conflict of interest');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Conflict of interest detected', $e->getMessage());
        }

        // 3. Complete verification and approve payout (286.2)
        $completed = $this->service->completeVerificationAssurance(
            'PRJ-PEATLAND-RESTORE-01',
            150000.0,
            'DOC-ASSURANCE-ISAE3000.PDF'
        );
        $this->assertTrue((bool) $completed->payout_approved);
        $this->assertEquals(150000.0, (float) $completed->verified_carbon_abatement_tons);
    }

    public function test_sustainability_linked_loan_margin_adjustment_formula(): void
    {
        // Issue loan with 5.50% base margin and 20% emissions reduction target (286.3 & 286.7)
        $this->service->issueSustainabilityLinkedLoan(
            facilityCode: 'SLL-FACILITY-2026-01',
            principalUsd: 50000000.0,
            baseMarginPct: 5.50,
            targetReductionPct: 20.0
        );

        // 1. KPI Met (25% reduction >= 20%) -> 0.25% discount to 5.25% (286.3)
        $metLoan = $this->service->evaluateLoanKpiMargin('SLL-FACILITY-2026-01', 25.0);
        $this->assertTrue((bool) $metLoan->kpi_threshold_met);
        $this->assertEquals(5.25, (float) $metLoan->adjusted_interest_rate_pct);

        // 2. KPI Missed (12% reduction < 20%) -> 0.25% penalty step-up to 5.75% (286.7 Edge Case)
        $missedLoan = $this->service->evaluateLoanKpiMargin('SLL-FACILITY-2026-01', 12.0);
        $this->assertFalse((bool) $missedLoan->kpi_threshold_met);
        $this->assertEquals(5.75, (float) $missedLoan->adjusted_interest_rate_pct);
    }

    public function test_esg_public_claim_governance_anti_greenwashing(): void
    {
        // 1. Valid claim with evidence mapping succeeds (286.4 & 286.8)
        $claim = $this->service->registerPublicClaim(
            claimCode: 'CLM-NET-ZERO-SMELTER-2026',
            claimStatement: 'Halmahera Smelter achieves 40% renewable energy mix in Q3',
            evidenceDocRef: 'DOC-PLN-GREEN-ENERGY-CERT-2026.PDF',
            legalApproved: true
        );
        $this->assertTrue((bool) $claim->legal_compliance_approved);
        $this->assertNotNull($claim->evidence_document_ref);

        // 2. Unsubstantiated claim missing evidence rejected (286.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Anti-greenwashing violation: Public ESG claim requires verified evidence');
        $this->service->registerPublicClaim('CLM-UNSUBSTANTIATED', '100% Eco-friendly mining', '');
    }

    public function test_esg_impact_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->assignVerifier('PRJ-AUD', 'VER-1', false);
        $this->service->completeVerificationAssurance('PRJ-AUD', 100.0, 'DOC.PDF');
        $this->service->issueSustainabilityLinkedLoan('SLL-AUD', 1000.0, 5.0, 10.0);
        $this->service->evaluateLoanKpiMargin('SLL-AUD', 15.0);
        $this->service->registerPublicClaim('CLM-AUD', 'Statement', 'DOC.PDF', true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unapproved public ESG claim
        DB::table('esg_public_claims')->insert([
            'claim_code' => 'CLM-UNAPPROVED-GREENWASH',
            'claim_statement' => 'Zero carbon footprint everywhere',
            'evidence_document_ref' => 'FAKE.PDF',
            'legal_compliance_approved' => false, // Discrepancy!
            'revalidation_expiry_date' => now()->addYear()->toDateString(),
            'is_expired' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
