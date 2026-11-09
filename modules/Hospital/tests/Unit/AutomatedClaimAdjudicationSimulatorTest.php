<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use Modules\Hospital\Domain\Services\AutomatedClaimAdjudicationSimulator;
use Tests\TestCase;

class AutomatedClaimAdjudicationSimulatorTest extends TestCase
{
    private AutomatedClaimAdjudicationSimulator $simulator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simulator = new AutomatedClaimAdjudicationSimulator;
    }

    public function test_bpjs_full_adjudication_simulation(): void
    {
        $resp = $this->simulator->simulateAdjudication(
            payerCode: 'BPJS',
            policyOrCardNumber: '0001234567890',
            billedAmountIdr: 16_875_000,
            cbgOrProcedureCode: 'I-4-10-II',
            supportingDocumentHashes: ['RESUME_DOC' => hash('sha256', 'RESUME_CONTENT')]
        );

        $this->assertSame('APPROVED_FULL', $resp['adjudication_status']);
        $this->assertSame(16_875_000, $resp['approved_amount_idr']);
        $this->assertSame(0, $resp['patient_copay_idr']);
        $this->assertNotEmpty($resp['authorization_code']);
    }

    public function test_private_insurance_deductible_and_copay_simulation(): void
    {
        $resp = $this->simulator->simulateAdjudication(
            payerCode: 'PRUDENTIAL',
            policyOrCardNumber: 'POL-998877',
            billedAmountIdr: 20_000_000,
            cbgOrProcedureCode: 'SURGERY_CARDIO',
            supportingDocumentHashes: ['RESUME_DOC' => hash('sha256', 'RESUME_CONTENT')]
        );

        $this->assertSame('APPROVED_PARTIAL', $resp['adjudication_status']);
        $this->assertGreaterThan(0, $resp['deductible_amount_idr']);
        $this->assertGreaterThan(0, $resp['patient_copay_idr']);
        $this->assertGreaterThan(0, $resp['approved_amount_idr']);
    }

    public function test_missing_documentation_yields_pending_status(): void
    {
        $resp = $this->simulator->simulateAdjudication(
            payerCode: 'BPJS',
            policyOrCardNumber: '0001234567890',
            billedAmountIdr: 10_000_000,
            cbgOrProcedureCode: 'I-4-10-I',
            supportingDocumentHashes: [] // Kosong
        );

        $this->assertSame('PENDING_DOCUMENTATION', $resp['adjudication_status']);
        $this->assertSame(0, $resp['approved_amount_idr']);
    }
}
