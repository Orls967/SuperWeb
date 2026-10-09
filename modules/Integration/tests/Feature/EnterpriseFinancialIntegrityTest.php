<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseFinancialIntegrityService;
use Tests\TestCase;

class EnterpriseFinancialIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseFinancialIntegrityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseFinancialIntegrityService::class);
    }

    public function test_financial_integrity_statement_and_auditor_opinion_flow(): void
    {
        // 480.1 Draft financial integrity statement with 0 exceptions across 115 audits
        $stmt = $this->service->draftIntegrityStatement(
            code: 'STMT-INTEGRITY-2026-FY',
            period: '2026-FY',
            auditsEvaluated: 115,
            exceptions: 0
        );

        $this->assertEquals('STMT-INTEGRITY-2026-FY', $stmt->statement_code);
        $this->assertEquals('draft', $stmt->status);

        // Sign statement
        $signed = $this->service->signStatement('STMT-INTEGRITY-2026-FY', 'Group Chief Financial Officer & Head of Internal Audit');
        $this->assertEquals('signed', $signed->status);

        // 480.3 & 480.6 Independent external auditor issues unqualified opinion
        $certified = $this->service->issueAuditorOpinion('STMT-INTEGRITY-2026-FY', 'unqualified', true);
        $this->assertEquals('certified_unqualified', $certified->status);
        $this->assertEquals('unqualified', $certified->auditor_opinion);

        // 480.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_exceptions_blocking_signature_and_unverified_sample_edge_cases(): void
    {
        // 480.5 Edge case: Statement with exceptions cannot be signed
        $this->service->draftIntegrityStatement('STMT-WITH-LEAK', '2026-Q3', 95, 2); // 2 exceptions!

        try {
            $this->service->signStatement('STMT-WITH-LEAK', 'CFO');
            $this->fail('Expected exception for signing statement with unresolved exceptions');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('zero exception policy violated', $e->getMessage());
        }

        // 480.6 Risk: Unverified sampling blocks auditor opinion
        $this->service->draftIntegrityStatement('STMT-CLEAN-NO-SAMPLE', '2026-Q4', 100, 0);
        $this->service->signStatement('STMT-CLEAN-NO-SAMPLE', 'CFO');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Independent sampling methodology has not been validated');

        $this->service->issueAuditorOpinion('STMT-CLEAN-NO-SAMPLE', 'unqualified', false);
    }
}
