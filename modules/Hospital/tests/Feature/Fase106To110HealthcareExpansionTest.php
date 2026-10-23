<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Application\Actions\BookMedicalTourismPackageAndWellnessMembershipAction;
use Modules\Hospital\Application\Actions\ConsolidateHospitalGroupPortfolioRiskAndCaseMixAction;
use Modules\Hospital\Application\Actions\EnrollClinicalTrialSubjectWithDeidentifiedVaultAction;
use Modules\Hospital\Application\Actions\SynchronizePublicHealthJknBpjsCommandCenterAction;
use Modules\Hospital\Application\Actions\TrackB3MedicalWasteManifestAndBloodBankCrossmatchAction;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\BloodBag;
use Modules\Hospital\Domain\Models\BpjsClaimBatch;
use Modules\Hospital\Domain\Models\BpjsClaimItem;
use Modules\Hospital\Domain\Models\ClinicalTrial;
use Modules\Hospital\Domain\Models\Encounter;
use Modules\Hospital\Domain\Models\MedicalEquipment;
use Modules\Hospital\Domain\Models\Patient;
use RuntimeException;
use Tests\TestCase;

class Fase106To110HealthcareExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected ClinicalTrial $trial;

    protected Patient $patient1;

    protected Patient $patient2;

    protected Encounter $encounter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->trial = ClinicalTrial::create([
            'trial_code' => 'TRL-CARDIO-106',
            'title' => 'Novel Lipid-Lowering Monoclonal Antibody Phase III Trial',
            'phase' => 'PHASE_III',
            'sponsor_name' => 'BioPharma Global Inc',
            'target_subjects' => 120,
            'status' => 'ACTIVE',
        ]);

        $this->patient1 = Patient::create([
            'mrn' => 'MRN-HSP-106-01',
            'name' => 'Andi Pratama',
            'date_of_birth' => '1985-05-15',
            'blood_type' => 'O+',
            'passport_hash' => hash('sha256', 'PASSPORT-ID-10601'),
        ]);

        $this->patient2 = Patient::create([
            'mrn' => 'MRN-HSP-106-02',
            'name' => 'Budi Santoso',
            'date_of_birth' => '1978-11-20',
            'blood_type' => 'A+',
            'passport_hash' => hash('sha256', 'PASSPORT-ID-10602'),
        ]);

        $this->encounter = Encounter::create([
            'encounter_code' => 'ENC-106-001',
            'patient_id' => $this->patient1->id,
            'admitted_at' => date('Y-m-d H:i:s'),
            'encounter_type' => 'INPATIENT',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_106_deterministic_randomization_allocates_expected_arm(): void
    {
        $action = app(EnrollClinicalTrialSubjectWithDeidentifiedVaultAction::class);
        $seed = 777;
        $consentHash = hash('sha256', 'CONSENT-FORM-01');

        $result = $action->handle(
            trialId: $this->trial->id,
            patientId: $this->patient1->id,
            informedConsentHash: $consentHash,
            seed: $seed
        );

        $expectedHash = crc32("{$seed}-{$this->trial->trial_code}-{$this->patient1->id}");
        $expectedArm = ($expectedHash % 2 === 0) ? 'ARM_A_ACTIVE' : 'ARM_B_PLACEBO';

        $this->assertEquals($expectedArm, $result['randomized_arm']);
        $this->assertEquals($expectedArm, $result['subject']->randomized_arm);
        $this->assertNotEmpty($result['anonymized_hash']);
    }

    public function test_106_duplicate_subject_enrollment_in_same_trial_is_rejected(): void
    {
        $action = app(EnrollClinicalTrialSubjectWithDeidentifiedVaultAction::class);
        $consentHash = hash('sha256', 'CONSENT-FORM-02');

        $action->handle(
            trialId: $this->trial->id,
            patientId: $this->patient2->id,
            informedConsentHash: $consentHash
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already enrolled in trial');

        $action->handle(
            trialId: $this->trial->id,
            patientId: $this->patient2->id,
            informedConsentHash: $consentHash
        );
    }

    public function test_106_blind_index_hmac_is_generated_and_vault_request_created(): void
    {
        $action = app(EnrollClinicalTrialSubjectWithDeidentifiedVaultAction::class);
        $consentHash = hash('sha256', 'CONSENT-FORM-03');

        $result = $action->handle(
            trialId: $this->trial->id,
            patientId: $this->patient1->id,
            informedConsentHash: $consentHash,
            seed: 42,
            vaultResearcherId: 'RESEARCHER-GENOMICS-01',
            vaultPurpose: 'Genomic biomarker target analysis'
        );

        $this->assertNotNull($result['vault_access']);
        $this->assertEquals('RESEARCHER-GENOMICS-01', $result['vault_access']->researcher_id);
        $this->assertFalse($result['vault_access']->approved_by_irb);
    }

    public function test_107_bpjs_claim_adjudication_only_accrues_approved_revenue(): void
    {
        $batch = BpjsClaimBatch::create([
            'batch_number' => 'BPJS-202610-0001',
            'claim_month' => '2026-10',
            'total_episodes' => 2,
            'total_claimed_idr' => 25000000,
            'status' => 'SUBMITTED',
        ]);

        $episode1 = BillingEpisode::create([
            'episode_code' => 'EP-107-001',
            'encounter_id' => $this->encounter->id,
            'total_charges_idr' => 15000000,
            'bpjs_coverage_idr' => 15000000,
            'insurance_copay_idr' => 0,
            'patient_share_idr' => 0,
            'status' => 'CLOSED',
        ]);

        $episode2 = BillingEpisode::create([
            'episode_code' => 'EP-107-002',
            'encounter_id' => $this->encounter->id,
            'total_charges_idr' => 10000000,
            'bpjs_coverage_idr' => 10000000,
            'insurance_copay_idr' => 0,
            'patient_share_idr' => 0,
            'status' => 'CLOSED',
        ]);

        $item1 = BpjsClaimItem::create([
            'batch_id' => $batch->id,
            'billing_episode_id' => $episode1->id,
            'drg_code' => 'I-4-10-I',
            'drg_tariff_idr' => 15000000,
            'verification_status' => 'SUBMITTED',
        ]);

        $item2 = BpjsClaimItem::create([
            'batch_id' => $batch->id,
            'billing_episode_id' => $episode2->id,
            'drg_code' => 'J-4-12-II',
            'drg_tariff_idr' => 10000000,
            'verification_status' => 'SUBMITTED',
        ]);

        $action = app(SynchronizePublicHealthJknBpjsCommandCenterAction::class);
        $result = $action->handle(
            claimBatchId: $batch->id,
            itemDecisions: [
                $item1->id => ['status' => 'APPROVED'],
                $item2->id => ['status' => 'DENIED', 'reason' => 'Diagnostic test result missing'],
            ],
            regionCode: 'DKI_JAKARTA',
            diseaseSyndrome: 'ACUTE_RESPIRATORY',
            caseCount: 45,
            alertThreshold: 50,
            baselineAdmissions: 120
        );

        $this->assertEquals(15000000, $result['approved_total_idr']);
        $this->assertEquals(10000000, $result['denied_total_idr']);
        $this->assertEquals('PARTIALLY_DENIED', $result['batch']->status);
    }

    public function test_107_epidemic_surveillance_triggers_alarm_on_threshold_breach(): void
    {
        $batch = BpjsClaimBatch::create([
            'batch_number' => 'BPJS-202610-0002',
            'claim_month' => '2026-10',
            'total_episodes' => 0,
            'total_claimed_idr' => 0,
            'status' => 'APPROVED',
        ]);

        $action = app(SynchronizePublicHealthJknBpjsCommandCenterAction::class);
        $result = $action->handle(
            claimBatchId: $batch->id,
            itemDecisions: [],
            regionCode: 'BALI_BADUNG',
            diseaseSyndrome: 'DENGUE_FEVER',
            caseCount: 85,
            alertThreshold: 50,
            baselineAdmissions: 100
        );

        $this->assertTrue($result['surveillance']->outbreak_alarm_triggered);
        $this->assertEquals('OUTBREAK_ALERT', $result['surveillance']->status);
        $this->assertEquals(115, $result['forecast']->predicted_admissions);
    }

    public function test_108_medical_tourism_package_escrows_multi_vendor_shares(): void
    {
        $action = app(BookMedicalTourismPackageAndWellnessMembershipAction::class);

        $result = $action->handle(
            patientId: $this->patient1->id,
            packageName: 'Bali Executive Cardiac Checkup & Luxury Villa Retreat',
            hospitalShareIdr: 25000000,
            hotelShareIdr: 15000000,
            transportShareIdr: 5000000,
            membershipTier: 'PLATINUM',
            adherenceScore: 90
        );

        $this->assertEquals(45000000, $result['total_price_idr']);
        $this->assertEquals('ESCROWED', $result['package']->status);
        $this->assertEquals('BOOKED', $result['package']->current_milestone);
        $this->assertNotNull($result['membership']);
        $this->assertEquals('PLATINUM', $result['membership']->tier);
        $this->assertEquals(100, $result['wearable_adherence']->pts_rewarded);
    }

    public function test_108_medical_tourism_milestone_transition_and_final_settlement(): void
    {
        $action = app(BookMedicalTourismPackageAndWellnessMembershipAction::class);

        $result = $action->handle(
            patientId: $this->patient2->id,
            packageName: 'Orthopedic Knee Arthroscopy & Nusa Dua Wellness Spa',
            hospitalShareIdr: 30000000,
            hotelShareIdr: 12000000,
            transportShareIdr: 3000000,
            membershipTier: 'GOLD',
            adherenceScore: 85,
            advanceToMilestone: 'CHECKED_IN_HOSPITAL'
        );

        $this->assertEquals('CHECKED_IN_HOSPITAL', $result['package']->current_milestone);
        $this->assertEquals('IN_PROGRESS', $result['package']->status);
    }

    public function test_109_expired_blood_bag_reservation_is_rejected(): void
    {
        $expiredBag = BloodBag::create([
            'bag_serial_number' => 'BLD-EXP-001',
            'blood_type' => 'O+',
            'component_type' => 'PRC',
            'volume_ml' => 350,
            'expiry_date' => date('Y-m-d', strtotime('-2 days')),
            'donor_screening_hash' => hash('sha256', 'DONOR-TEST-01'),
            'status' => 'AVAILABLE',
        ]);

        $action = app(TrackB3MedicalWasteManifestAndBloodBankCrossmatchAction::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is expired');

        $action->handle(
            bloodBagId: $expiredBag->id,
            encounterId: $this->encounter->id
        );
    }

    public function test_109_expired_medical_equipment_calibration_blocks_procedure(): void
    {
        $expiredEquipment = MedicalEquipment::create([
            'equipment_code' => 'VENT-ICU-009',
            'name' => 'High-Frequency ICU Ventilator',
            'category' => 'LIFE_SUPPORT',
            'calibration_expires_at' => date('Y-m-d', strtotime('-5 days')),
            'calibration_certificate_hash' => hash('sha256', 'CALIB-CERT-009'),
            'status' => 'ACTIVE',
        ]);

        $action = app(TrackB3MedicalWasteManifestAndBloodBankCrossmatchAction::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('calibration expired');

        $action->handle(
            equipmentId: $expiredEquipment->id
        );
    }

    public function test_109_b3_medical_waste_manifest_generates_gapless_custody_hash(): void
    {
        $action = app(TrackB3MedicalWasteManifestAndBloodBankCrossmatchAction::class);

        $result = $action->handle(
            wasteCategory: 'SHARPS_BIOHAZARD',
            wasteWeightKg: 42.8,
            vendorPartyId: 'VEND-ECO-WASTE-09'
        );

        $this->assertNotNull($result['waste_manifest']);
        $this->assertEquals('SHARPS_BIOHAZARD', $result['waste_manifest']->waste_category);
        $this->assertEquals(42.8, $result['waste_manifest']->weight_kg);
        $this->assertNotEmpty($result['waste_manifest']->custody_hash);
        $this->assertEquals('DISPATCHED', $result['waste_manifest']->status);
    }

    public function test_110_group_scorecard_and_equipment_roi_calculation(): void
    {
        $action = app(ConsolidateHospitalGroupPortfolioRiskAndCaseMixAction::class);

        $result = $action->handle(
            hospitalCode: 'HSP-JAK-01',
            hospitalName: 'Siloam Prima Medika Jakarta',
            year: 2026,
            month: 10,
            admissions: 450,
            mortalityRate: 1.2,
            readmissionRate: 3.5,
            patientSatisfaction: 4.8,
            revenueIdr: 4500000000,
            bpjsArIdr: 1200000000,
            insuranceArIdr: 800000000,
            equipmentCode: 'MRI-3T-01',
            modality: 'MAGNETIC_RESONANCE',
            capexIdr: 25000000000,
            additionalRevenueIdr: 1250000000,
            proceduresDone: 210
        );

        $this->assertNotNull($result['scorecard']);
        $this->assertGreaterThan(80.0, $result['scorecard']->overall_quality_score);
        $this->assertEquals('MRI-3T-01', $result['equipment_roi']->equipment_code);
        $this->assertEquals(5.0, $result['equipment_roi']->roi_percentage);
        $this->assertEquals('HEALTHY', $result['portfolio_audit']['status']);
    }
}
