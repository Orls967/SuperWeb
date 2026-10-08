<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\RegulatoryTaxOperationsService;
use Tests\TestCase;

class RegulatoryTaxOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected RegulatoryTaxOperationsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RegulatoryTaxOperationsService::class);
    }

    public function test_obligation_and_tax_provision_flow(): void
    {
        // 404.1
        $obligation = $this->service->scheduleObligation(
            obligationCode: 'OBL-ID-OJK-001',
            jurisdiction: 'INDONESIA',
            title: 'Laporan Berkala Lembaga Jasa Keuangan',
            dueDate: '2026-10-31'
        );

        $this->assertEquals('OBL-ID-OJK-001', $obligation->obligation_code);

        // Submit obligation with evidence
        $submitted = $this->service->submitObligation(
            obligationCode: 'OBL-ID-OJK-001',
            approver: 'Legal Director',
            evidenceHash: 'hash_ojk_evidence_992182'
        );

        $this->assertEquals('submitted', $submitted->status);

        // 404.3 Tax provision governance
        $provision = $this->service->registerTaxProvision(
            provisionCode: 'TAX-PROV-2026',
            fiscalYear: '2026',
            estimatedAmount: 5000000000.00,
            uncertainTaxPositionAmount: 250000000.00,
            sourceLineage: ['LEDGER_CORE_TAX_EXPENSE', 'CIT_RETURN_ESTIMATE'],
            approvedByCfo: true
        );

        $this->assertTrue((bool) $provision->approved_by_cfo);

        // 404.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_late_filing_requires_root_cause_edge_case(): void
    {
        $this->service->scheduleObligation(
            obligationCode: 'OBL-SG-MAS-002',
            jurisdiction: 'SINGAPORE',
            title: 'MAS Capital Adequacy Quarterly',
            dueDate: '2026-09-30'
        );

        // 404.5 Edge case: late filing without root cause is rejected
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Late filing strictly requires root cause documentation');

        $this->service->submitObligation(
            obligationCode: 'OBL-SG-MAS-002',
            approver: 'Compliance Officer',
            evidenceHash: 'hash_mas_8812',
            isLate: true,
            rootCause: null // Missing!
        );
    }
}
