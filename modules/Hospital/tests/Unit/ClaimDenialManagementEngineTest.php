<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use Modules\Hospital\Domain\Services\ClaimDenialManagementEngine;
use Tests\TestCase;

class ClaimDenialManagementEngineTest extends TestCase
{
    private ClaimDenialManagementEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new ClaimDenialManagementEngine;
    }

    public function test_denial_evaluation_with_complete_evidence_has_high_overturn_probability(): void
    {
        $eval = $this->engine->evaluateDenialAndAppealViability(
            denialReasonCode: 'D01_MISSING_CLINICAL_RESUME',
            disputedAmountIdr: 15_000_000,
            hasSignedDoctorResume: true,
            hasDiagnosticEvidence: true,
            daysSinceDischarge: 5
        );

        $this->assertTrue($eval['is_appealable']);
        $this->assertGreaterThan(0.80, $eval['estimated_overturn_probability']);
        $this->assertGreaterThan(12_000_000, $eval['estimated_recoverable_amount_idr']);
        $this->assertSame('EXPEDITE_DOCTOR_SIGNATURE_AND_UPLOAD', $eval['recommended_strategy']);
    }

    public function test_appeal_dossier_generation_structure(): void
    {
        $dossier = $this->engine->generateAppealDossier(
            claimNumber: 'CLM-20261109-001122',
            dpjpDoctorName: 'dr. Haryo Tejo, Sp.JP(K)',
            clinicalRationale: 'Pasien mengalami sindrom koroner akut elevasi segmen ST yang memerlukan revaskularisasi emergensi intra-arteri sesuai indikasi klinis ACC/AHA Class I.',
            attachedEvidenceCodes: ['EKG-12-LEAD', 'TROPONIN-I-LAB', 'CORONARY-ANGIO-DICOM']
        );

        $this->assertStringStartsWith('APL-', $dossier['appeal_dossier_id']);
        $this->assertSame(3, $dossier['attached_evidence_count']);
        $this->assertSame('READY_FOR_SUBMISSION', $dossier['status']);
    }
}
