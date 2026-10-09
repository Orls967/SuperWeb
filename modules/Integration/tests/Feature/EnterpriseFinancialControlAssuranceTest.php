<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseFinancialControlAssuranceService;
use Tests\TestCase;

class EnterpriseFinancialControlAssuranceTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseFinancialControlAssuranceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseFinancialControlAssuranceService::class);
    }

    public function test_financial_control_testing_and_assertion_signoff_flow(): void
    {
        // 458.1 & 458.2 Register material financial process
        $proc = $this->service->registerProcess(
            code: 'FIN-REV-RECOG-01',
            title: 'Multi-Currency Revenue Recognition Engine',
            isMaterial: true
        );

        $this->assertEquals('FIN-REV-RECOG-01', $proc->process_code);
        $this->assertFalse((bool) $proc->assurance_tested);

        // Record independent assurance testing
        $tested = $this->service->recordAssuranceTesting('FIN-REV-RECOG-01', true);
        $this->assertTrue((bool) $tested->assurance_tested);

        // Sign management assertion
        $signed = $this->service->signManagementAssertion('FIN-REV-RECOG-01');
        $this->assertEquals('signed_effective', $signed->management_assertion_status);

        // 458.3 & 458.6 Log deficiency with documented disclosure consideration
        $def = $this->service->logDeficiency(
            defCode: 'DEF-SPREADSHEET-MACRO',
            processCode: 'FIN-REV-RECOG-01',
            severity: 'significant_deficiency',
            disclosureConsidered: true
        );

        $this->assertTrue((bool) $def->disclosure_consideration_documented);

        // 458.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_untested_material_process_assertion_and_undisclosed_weakness_blocked_edge_cases(): void
    {
        // 458.5 Edge case: Material process without assurance testing blocks assertion sign-off
        $this->service->registerProcess('FIN-TREASURY-SETTLE', 'Forex Treasury Settlement', true);

        try {
            $this->service->signManagementAssertion('FIN-TREASURY-SETTLE');
            $this->fail('Expected exception for untested material process');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('has not undergone independent assurance testing', $e->getMessage());
        }

        // 458.6 Risk: Material weakness without disclosure consideration is blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires mandatory documented disclosure consideration');

        $this->service->logDeficiency(
            defCode: 'DEF-UNRECONCILED-SUSPENSE',
            processCode: 'FIN-TREASURY-SETTLE',
            severity: 'material_weakness',
            disclosureConsidered: false // Undocumented!
        );
    }
}
