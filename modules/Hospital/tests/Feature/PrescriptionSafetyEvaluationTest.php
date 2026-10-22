<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Application\Services\PrescriptionSafetyEvaluator;
use Modules\Hospital\Domain\Models\AllergyMatrix;
use Modules\Hospital\Domain\Models\Patient;
use Tests\TestCase;

class PrescriptionSafetyEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private PrescriptionSafetyEvaluator $evaluator;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = app(PrescriptionSafetyEvaluator::class);

        $this->patient = Patient::create([
            'mrn' => 'MRN-SAFETY-01',
            'name' => 'Jane Safety Patient',
            'date_of_birth' => '1985-05-20',
            'blood_type' => 'A+',
            'encrypted_allergies' => ['PENICILLIN'],
            'encrypted_chronic_diagnoses' => ['CHRONIC_KIDNEY_DISEASE_STAGE_4'],
            'passport_hash' => hash('sha256', 'PASSPORT-MRN-SAFETY-01'),
        ]);
    }

    public function test_direct_allergy_triggers_critical_contraindication(): void
    {
        $evaluation = $this->evaluator->evaluateSafety(
            patient: $this->patient,
            drugCode: 'PENICILLIN',
            dosageMg: 500
        );

        $this->assertFalse($evaluation['is_safe']);
        $this->assertSame('CRITICAL_CONTRAINDICATION', $evaluation['risk_level']);
        $this->assertStringContainsString('Direct anaphylactic/allergic contraindication', $evaluation['reasons'][0]);
    }

    public function test_cross_allergy_class_blocks_amoxicillin_for_penicillin_allergic_patient(): void
    {
        $evaluation = $this->evaluator->evaluateSafety(
            patient: $this->patient,
            drugCode: 'AMOXICILLIN',
            dosageMg: 250
        );

        $this->assertFalse($evaluation['is_safe']);
        $this->assertSame('CRITICAL_CONTRAINDICATION', $evaluation['risk_level']);
        $this->assertStringContainsString('Cross-reactivity alert', $evaluation['reasons'][0]);
    }

    public function test_renal_clearance_under_thirty_blocks_nephrotoxic_drugs(): void
    {
        // Patient has severe renal impairment: CrCl = 22 mL/min
        $evaluation = $this->evaluator->evaluateSafety(
            patient: $this->patient,
            drugCode: 'METFORMIN',
            dosageMg: 500,
            creatinineClearanceMlLMin: 22
        );

        $this->assertFalse($evaluation['is_safe']);
        $this->assertStringContainsString('Renal impairment contraindication', $evaluation['reasons'][0]);
    }

    public function test_safe_medication_passes_evaluation(): void
    {
        $evaluation = $this->evaluator->evaluateSafety(
            patient: $this->patient,
            drugCode: 'PARACETAMOL',
            dosageMg: 500,
            creatinineClearanceMlLMin: 90
        );

        $this->assertTrue($evaluation['is_safe']);
        $this->assertSame('LOW_RISK', $evaluation['risk_level']);
        $this->assertEmpty($evaluation['reasons']);
    }

    public function test_allergy_matrix_database_records_are_evaluated(): void
    {
        AllergyMatrix::create([
            'patient_id' => $this->patient->id,
            'allergen_code' => 'SULFONAMIDE',
            'severity' => 'SEVERE_ANAPHYLAXIS',
            'reaction_description' => 'Stevens-Johnson Syndrome risk',
        ]);

        $evaluation = $this->evaluator->evaluateSafety(
            patient: $this->patient,
            drugCode: 'COTRIMOXAZOLE',
            dosageMg: 480
        );

        $this->assertFalse($evaluation['is_safe']);
        $this->assertStringContainsString('Cross-reactivity alert', $evaluation['reasons'][0]);
    }
}
