<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CommonAuditReconciliationService;
use Tests\TestCase;

class CommonAuditReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected CommonAuditReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CommonAuditReconciliationService::class);
    }

    public function test_upstream_audit_failure_holds_downstream_audit_edge_case(): void
    {
        // 1. Upstream domain audit fails, downstream audit is held (389.2, 389.4, 389.5 Edge Case)
        try {
            $this->service->scheduleDomainAudit(
                runCode: 'AUD-FINANCE-P&L-01',
                domainName: 'FINANCE_P&L',
                upstreamDomain: 'PROCUREMENT_EXPENSES',
                upstreamAuditPassed: false // Upstream failed!
            );
            $this->fail('Expected exception for downstream audit scheduling when upstream audit failed');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('downstream audit \'FINANCE_P&L\' held to prevent incomplete signoff', $e->getMessage());
        }

        // Verify downstream run record shows not permitted
        $run = DB::table('global_common_audit_run_schedulers')->where('run_code', 'AUD-FINANCE-P&L-01')->first();
        $this->assertNotNull($run);
        $this->assertFalse((bool) $run->downstream_audit_permitted);
        $this->assertEquals('1', $run->exit_code);

        // 2. Successful upstream allows downstream audit (389.2 & 389.4)
        $validRun = $this->service->scheduleDomainAudit(
            runCode: 'AUD-FINANCE-P&L-02',
            domainName: 'FINANCE_P&L',
            upstreamDomain: 'PROCUREMENT_EXPENSES',
            upstreamAuditPassed: true
        );
        $this->assertTrue((bool) $validRun->downstream_audit_permitted);
        $this->assertEquals('0', $validRun->exit_code);
    }

    public function test_evidence_pack_pii_redaction_and_checksum_verification_risk(): void
    {
        // 1. Evidence with unredacted PII is blocked (389.3, 389.4, 389.6 Risk)
        try {
            $this->service->buildEvidencePack(
                packCode: 'PCK-EVIDENCE-AUDIT-01',
                evidenceData: 'Customer transaction list with RAW_PII_SSN: 000-11-2222',
                piiRedacted: false
            );
            $this->fail('Expected exception for unredacted PII evidence pack');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unredacted PII detected in evidence payload', $e->getMessage());
        }

        // 2. Redacted evidence pack succeeds with verified checksum (389.3 & 389.4)
        $pack = $this->service->buildEvidencePack(
            packCode: 'PCK-EVIDENCE-AUDIT-02',
            evidenceData: 'Customer transaction list with REDACTED_IDENTITY: [HASHED]',
            piiRedacted: true
        );
        $this->assertTrue((bool) $pack->pii_redacted);
        $this->assertTrue((bool) $pack->checksum_verified);
        $this->assertNotEmpty($pack->evidence_checksum);
    }

    public function test_central_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->scheduleDomainAudit('R-AUD', 'DOM1', null, true);
        $this->service->buildEvidencePack('P-AUD', 'CLEAN DATA', true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unredacted PII evidence pack
        DB::table('global_common_audit_evidence_packs')->insert([
            'pack_code' => 'P-DEFECT-PII-LEAK',
            'evidence_checksum' => 'FAKE-HASH',
            'pii_redacted' => false, // Discrepancy!
            'checksum_verified' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
