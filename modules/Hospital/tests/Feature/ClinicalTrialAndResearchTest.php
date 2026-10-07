<?php

namespace Modules\Hospital\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hospital\Application\Services\ClinicalTrialAndResearchService;
use Modules\Hospital\Domain\Models\ClinicalTrial;
use Modules\Hospital\Domain\Models\Patient;
use RuntimeException;
use Tests\TestCase;

class ClinicalTrialAndResearchTest extends TestCase
{
    use RefreshDatabase;

    protected ClinicalTrialAndResearchService $service;

    protected ClinicalTrial $trial;

    protected Patient $patient1;

    protected Patient $patient2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ClinicalTrialAndResearchService::class);

        $this->trial = ClinicalTrial::create([
            'trial_code' => 'TRL-CARDIO-01',
            'title' => 'Cardiovascular Lipid-Lowering Phase III Study',
            'phase' => 'PHASE_III',
            'sponsor_name' => 'Pharma Global Corp',
            'target_subjects' => 100,
            'status' => 'ACTIVE',
        ]);

        $this->patient1 = Patient::create([
            'mrn' => 'MRN-TRL-01',
            'name' => 'Trial Subject Candidate 1',
            'date_of_birth' => '1980-03-12',
            'blood_type' => 'O+',
            'passport_hash' => hash('sha256', 'PASSPORT-TRL-01'),
        ]);

        $this->patient2 = Patient::create([
            'mrn' => 'MRN-TRL-02',
            'name' => 'Trial Subject Candidate 2',
            'date_of_birth' => '1975-09-25',
            'blood_type' => 'AB+',
            'passport_hash' => hash('sha256', 'PASSPORT-TRL-02'),
        ]);

        // Register hospital trial accounts
        $accounts = [
            'hsp:trial_sponsor_receivable:IDR' => 'asset',
            'hsp:trial_research_revenue:IDR' => 'revenue',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Hospital {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_106_6_a_duplicate_subject_enrollment_in_same_study_is_rejected(): void
    {
        $consentHash = hash('sha256', 'CONSENT-PATIENT-01');

        $this->service->enrollSubject($this->trial, $this->patient1, $consentHash);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already enrolled in trial');

        $this->service->enrollSubject($this->trial, $this->patient1, $consentHash);
    }

    public function test_106_6_b_deterministic_randomization_yields_identical_allocation(): void
    {
        $consentHash = hash('sha256', 'CONSENT-PATIENT-02');
        $seed = 777;

        $subject = $this->service->enrollSubject($this->trial, $this->patient2, $consentHash, $seed);

        $expectedHash = crc32("{$seed}-{$this->trial->trial_code}-{$this->patient2->id}");
        $expectedArm = ($expectedHash % 2 === 0) ? 'ARM_A_ACTIVE' : 'ARM_B_PLACEBO';

        $this->assertEquals($expectedArm, $subject->randomized_arm);
    }

    public function test_106_6_c_data_vault_access_without_approval_is_denied(): void
    {
        $accessReq = $this->service->requestVaultAccess(
            $this->trial,
            'RESEARCHER-BIO-99',
            'Genomic sequencing biomarker analysis'
        );

        $this->assertFalse($accessReq->approved_by_irb);

        // Attempting to query vault must fail
        try {
            $this->service->queryVault($accessReq);
            $this->fail('Expected unauthorized exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Access denied: Unauthorized attempt to access encrypted genomic', $e->getMessage());
        }

        // Now approve with IRB committee hash
        $approved = $this->service->approveVaultAccess($accessReq, hash('sha256', 'IRB-APPROVAL-COMM-01'));
        $this->assertTrue($approved->approved_by_irb);

        $data = $this->service->queryVault($approved);
        $this->assertEquals('SUCCESS', $data['status']);
    }

    public function test_106_6_d_and_e_sponsor_milestone_billing_and_ledger_balance(): void
    {
        $this->service->billSponsorMilestone($this->trial, 'First 25 Subjects Enrolled', 500_000_000);

        $ar = LedgerAccount::where('code', 'hsp:trial_sponsor_receivable:IDR')->first();
        $rev = LedgerAccount::where('code', 'hsp:trial_research_revenue:IDR')->first();

        $this->assertEquals('500000000', (string) $ar->cached_balance);
        $this->assertEquals('-500000000', (string) $rev->cached_balance);
    }
}
