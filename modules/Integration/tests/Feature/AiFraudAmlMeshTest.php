<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\AiFraudAmlMeshService;
use Tests\TestCase;

/**
 * Fase 200 — AI: Fraud, AML & Anomaly Detection Mesh 30 Lini Tests
 *
 * Covers:
 *  (a) composite fraud score aggregates multiple cross-line anomaly signals
 *  (b) freeze account requires authorized compliance officer
 *  (c) SAR filings maintain gapless incremental sequence numbers
 *  (d) fraud:audit = 0 discrepancy
 */
class AiFraudAmlMeshTest extends TestCase
{
    use RefreshDatabase;

    protected AiFraudAmlMeshService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AiFraudAmlMeshService::class);
    }

    /**
     * (a) Cross-line composite fraud scoring.
     */
    public function test_composite_fraud_scoring(): void
    {
        $signals = [
            ['source_line' => 'L01_FINANCE', 'type' => 'SUSPICIOUS_PAYMENT_BURST', 'weight' => 45.0],
            ['source_line' => 'L07_INSURANCE', 'type' => 'RAPID_CONSECUTIVE_CLAIMS', 'weight' => 40.0],
        ];

        $case = $this->service->createFraudCase('ENT-USER-9901', $signals);
        $this->assertSame('OPEN', $case->case_status);
        $this->assertEquals(85.00, (float) $case->composite_fraud_score);
    }

    /**
     * (b) Freeze account approval requirement.
     */
    public function test_fraud_freeze_approval_requirement(): void
    {
        $case = $this->service->createFraudCase('ENT-MERCHANT-55', [['weight' => 90.0]]);

        // 1. Freeze without approval -> Exception
        try {
            $this->service->freezeAccount($case->case_code, '   ');
            $this->fail('Expected exception for unapproved freeze.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Account freezing requires approval', $e->getMessage());
        }

        // 2. Approved freeze -> FROZEN
        $frozen = $this->service->freezeAccount($case->case_code, 'Kepala Kepatuhan AML');
        $this->assertSame('FROZEN', $frozen->case_status);
        $this->assertSame('Kepala Kepatuhan AML', $frozen->freeze_approved_by);
    }

    /**
     * (c) Gapless AML SAR sequence numbering.
     */
    public function test_gapless_sar_sequence_filings(): void
    {
        $sar1 = $this->service->fileAmlSar('CAS-001', 500000000.0);
        $sar2 = $this->service->fileAmlSar('CAS-002', 750000000.0);

        $this->assertSame(1, (int) $sar1->sar_sequence_number);
        $this->assertSame('SAR-PPATK-2026-000001', $sar1->sar_reference_code);

        $this->assertSame(2, (int) $sar2->sar_sequence_number);
        $this->assertSame('SAR-PPATK-2026-000002', $sar2->sar_reference_code);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_fraud_aml_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
