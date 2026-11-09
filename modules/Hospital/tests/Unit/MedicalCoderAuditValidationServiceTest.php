<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use Modules\Hospital\Domain\Services\MedicalCoderAuditValidationService;
use Tests\TestCase;

class MedicalCoderAuditValidationServiceTest extends TestCase
{
    private MedicalCoderAuditValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validationService = new MedicalCoderAuditValidationService();
    }

    public function test_symptom_as_primary_diagnosis_triggers_violation(): void
    {
        // R10.0 (Abdominal Pain Akut - Gejala) sebagai primer, padahal ada K35.8 (Apendisitis Akut - Definitif)
        $audit = $this->validationService->auditMedicalCoding(
            primaryDiagnosisIcd10: 'R10.0',
            secondaryDiagnosesIcd10: ['K35.8'],
            proceduresIcd9: ['47.01'],
            hasSupportingDiagnosticReport: true
        );

        $this->assertFalse($audit['is_valid']);
        $this->assertSame('REVISION_REQUIRED', $audit['audit_status']);
        $this->assertContains('RULE_R_SYMPTOM_AS_PRIMARY', $audit['violation_codes']);
    }

    public function test_cesarean_section_without_procedure_triggers_missing_procedure(): void
    {
        // O82 (Seksio Sesarea) tapi tanpa prosedur bedah 74.x
        $audit = $this->validationService->auditMedicalCoding(
            primaryDiagnosisIcd10: 'O82.0',
            secondaryDiagnosesIcd10: [],
            proceduresIcd9: ['88.78'], // Hanya USG diagnostik
            hasSupportingDiagnosticReport: true
        );

        $this->assertFalse($audit['is_valid']);
        $this->assertContains('RULE_MISSING_REQUIRED_PROCEDURE', $audit['violation_codes']);
    }

    public function test_clean_compliant_coding_passes_audit(): void
    {
        $audit = $this->validationService->auditMedicalCoding(
            primaryDiagnosisIcd10: 'I21.0',
            secondaryDiagnosesIcd10: ['I10'],
            proceduresIcd9: ['36.06'],
            hasSupportingDiagnosticReport: true
        );

        $this->assertTrue($audit['is_valid']);
        $this->assertSame('CLEAN_APPROVED', $audit['audit_status']);
        $this->assertEmpty($audit['violation_codes']);
    }
}
