<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Domain\Models\AllergyMatrix;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\DrugInteractionRule;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Services\ClinicalDecisionSupportRuleEngine;
use Modules\Hospital\Domain\Services\DigitalPrescriptionSignatureVerifier;
use Modules\Hospital\Domain\Services\DrugInteractionMatrixService;
use Modules\Hospital\Domain\Services\HighAlertLookAlikeSoundAlikeGuard;
use Modules\Hospital\Domain\Services\PediatricGeriatricDoseSafetyGuard;
use Modules\Hospital\Domain\Services\RenalHepaticDosingAdjustmentCalculator;
use Modules\Hospital\Domain\ValueObjects\MedicineDosageRegimen;
use Tests\TestCase;

class ClinicalDecisionSupportComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['role' => 'doctor']);
        $poly = Polyclinic::create([
            'code' => 'POLY-INT-TEST',
            'name' => 'Poli Penyakit Dalam',
        ]);

        $this->patient = Patient::create([
            'mrn' => 'MRN-CDS-TEST-001',
            'name' => 'Bapak Ahmad CDS',
            'date_of_birth' => '1955-03-12', // Geriatric (71 tahun)
            'blood_type' => 'O_POSITIVE',
            'encrypted_allergies' => ['PENICILLIN'],
            'encrypted_chronic_diagnoses' => ['CHRONIC_KIDNEY_DISEASE_STAGE_4', 'HYPERTENSION'],
            'passport_hash' => hash('sha256', 'PASSPORT-MRN-CDS-TEST-001'),
        ]);

        $this->doctor = Doctor::create([
            'user_id' => $user->id,
            'polyclinic_id' => $poly->id,
            'doctor_code' => 'DOC-CDS-001',
            'name' => 'dr. Hendra Spesialis Farmakologi Sp.FK',
            'sip_number' => 'SIP/998/IDI/2026',
            'specialization' => 'INTERNAL_MEDICINE',
            'is_active' => true,
        ]);

        DrugInteractionRule::create([
            'drug_a_code' => 'WARFARIN',
            'drug_a_name' => 'Warfarin Sodium',
            'drug_b_code' => 'ASPIRIN',
            'drug_b_name' => 'Aspirin Cardio',
            'interaction_severity' => 'MAJOR',
            'clinical_mechanism' => 'Peningkatan risiko pendarahan gastrointestinal mayor',
            'recommendation' => 'Hindari kombinasi atau pantau INR ketat setiap 48 jam',
            'is_active' => true,
        ]);

        AllergyMatrix::create([
            'patient_id' => $this->patient->id,
            'allergen_code' => 'PENICILLIN',
            'severity' => 'ANAPHYLAXIS',
            'reaction_description' => 'Severe bronchospasm',
        ]);
    }

    public function test_drug_interaction_matrix_detects_major_interaction(): void
    {
        $matrixService = app(DrugInteractionMatrixService::class);
        $pairResult = $matrixService->checkPair('WARFARIN', 'ASPIRIN');

        $this->assertTrue($pairResult['has_interaction']);
        $this->assertSame('MAJOR', $pairResult['severity']);
        $this->assertStringContainsString('pendarahan', $pairResult['mechanism']);

        $scanResult = $matrixService->scanPrescriptionList(['WARFARIN', 'ASPIRIN', 'PARACETAMOL']);
        $this->assertCount(1, $scanResult);
        $this->assertSame('WARFARIN', $scanResult[0]['drug_a']);
        $this->assertSame('ASPIRIN', $scanResult[0]['drug_b']);
    }

    public function test_renal_clearance_adjustment_calculator_flags_contraindication(): void
    {
        $renalService = app(RenalHepaticDosingAdjustmentCalculator::class);

        // CrCl = 25 mL/min
        $recommendation = $renalService->getRenalDosingRecommendation('METFORMIN', 25.0);
        $this->assertTrue($recommendation['is_contraindicated']);
        $this->assertSame(0.0, (float) $recommendation['dose_fraction']);

        $gentamicinRec = $renalService->getRenalDosingRecommendation('GENTAMICIN', 30.0);
        $this->assertSame(48, $gentamicinRec['interval_hours']);
    }

    public function test_lasa_look_alike_sound_alike_guard_warns_physician(): void
    {
        $lasaGuard = app(HighAlertLookAlikeSoundAlikeGuard::class);

        $this->assertTrue($lasaGuard->isHighAlert('WARFARIN'));
        $eval = $lasaGuard->getLasaEvaluation('HYDRALAZINE');
        $this->assertTrue($eval['is_lasa']);
        $this->assertSame('hydrALAZINE', $eval['tall_man_name']);
        $this->assertSame('hydrOXYzine', $eval['confusable_with']);
    }

    public function test_pediatric_geriatric_dose_safety_guard_adjusts_for_elderly(): void
    {
        $doseGuard = app(PediatricGeriatricDoseSafetyGuard::class);

        // Geriatric Beers Criteria
        $geriatricAudit = $doseGuard->evaluateGeriatricSafety('DIAZEPAM', 71);
        $this->assertTrue($geriatricAudit['is_inappropriate']);
        $this->assertNotNull($geriatricAudit['beers_alert']);

        // Pediatric evaluation
        $pedAudit = $doseGuard->evaluatePediatricSafety('TETRACYCLINE', 6, 72);
        $this->assertFalse($pedAudit['is_safe']);
        $this->assertNotEmpty($pedAudit['contraindications']);
    }

    public function test_digital_signature_verification_succeeds_and_detects_tampering(): void
    {
        $signer = app(DigitalPrescriptionSignatureVerifier::class);

        $prescriptionData = [
            'prescription_code' => 'RX-CDS-8812',
            'patient_mrn' => $this->patient->mrn,
            'items' => [
                ['drug_code' => 'AMLODIPINE', 'quantity' => 30],
            ],
            'timestamp' => '2026-11-09T10:00:00Z',
        ];

        $signatureEnvelope = $signer->signPrescription(
            $this->doctor->id,
            $this->doctor->sip_number,
            $prescriptionData
        );
        $this->assertNotNull($signatureEnvelope->signatureHash);

        $isValid = $signer->verifySignature($signatureEnvelope, $prescriptionData);
        $this->assertTrue($isValid);

        // Tampering
        $tamperedData = $prescriptionData;
        $tamperedData['items'][0]['quantity'] = 100;
        $isTamperedValid = $signer->verifySignature($signatureEnvelope, $tamperedData);
        $this->assertFalse($isTamperedValid);
    }

    public function test_clinical_decision_support_full_pipeline_orchestration(): void
    {
        $cdse = app(ClinicalDecisionSupportRuleEngine::class);

        $regimens = [
            new MedicineDosageRegimen(
                drugCode: 'PENICILLIN_G',
                drugName: 'Penicillin G Sodium',
                dosePerAdministrationMg: 500.0,
                administrationsPerDay: 3,
                durationDays: 7,
                maxSafeDailyDoseMg: 2000.0
            ),
        ];

        $evaluation = $cdse->evaluateSafety(
            patient: $this->patient,
            regimens: $regimens,
            serumCreatinineMgDl: 1.8,
            patientWeightKg: 65.0
        );

        $this->assertFalse($evaluation->isDispensable);
        $this->assertNotEmpty($evaluation->contraindications);
    }
}
