<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\GovernanceDataRetentionDiscoveryService;
use Tests\TestCase;

class GovernanceDataRetentionDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected GovernanceDataRetentionDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GovernanceDataRetentionDiscoveryService::class);
    }

    public function test_financial_ledger_records_strictly_immutable_and_cannot_be_disposed(): void
    {
        // 1. Financial ledger record created (291.1, 291.3, 291.5)
        $this->service->registerRecord(
            recordCode: 'REC-LEDGER-2026-001',
            recordClass: 'FINANCIAL_LEDGER',
            retentionExpiryDate: '2036-12-31'
        );

        // 2. Attempting disposition on financial ledger is strictly blocked (291.5)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Financial ledger records are strictly immutable and cannot be deleted');
        $this->service->executeRetentionDisposition('REC-LEDGER-2026-001');
    }

    public function test_legal_hold_prevents_disposition_and_skips_record(): void
    {
        // 1. Legal hold placed for matter (291.2 & 291.5)
        $this->service->placeLegalHold(
            matterCode: 'HOLD-TAX-LITIGATION-2026',
            title: 'Corporate Tax Dispute 2026',
            targetEntity: 'AUTOSERVE_HOLDINGS',
            legalApproverId: 'LEGAL_GC_01'
        );

        // 2. Customer record tagged with legal hold (291.2)
        $this->service->registerRecord(
            recordCode: 'REC-PII-AUDIT-TAX',
            recordClass: 'CUSTOMER_PII',
            retentionExpiryDate: '2026-01-01',
            associatedHoldMatter: 'HOLD-TAX-LITIGATION-2026'
        );

        // 3. Disposition job skips record subject to active legal hold (291.5 & 291.6 Edge Case)
        $result = $this->service->executeRetentionDisposition('REC-PII-AUDIT-TAX');
        $this->assertEquals('SKIPPED_LEGAL_HOLD', $result->disposition_status);

        // 4. Normal record without legal hold is successfully anonymized upon expiry (291.3)
        $this->service->registerRecord('REC-PII-NORMAL', 'CUSTOMER_PII', '2026-01-01');
        $normalResult = $this->service->executeRetentionDisposition('REC-PII-NORMAL');
        $this->assertEquals('ANONYMIZED', $normalResult->disposition_status);
    }

    public function test_ediscovery_hash_verified_export_and_recipient_recording(): void
    {
        // Export documents with hash verification and recipient logging (291.4 & 291.7)
        $export = $this->service->exportEdiscoveryCorpus(
            exportCode: 'EXP-DOJ-INQUIRY-01',
            matterScope: 'MINING_CONCESSION_COMMUNICATIONS_2025_2026',
            authorizedRecipientId: 'EXTERNAL_COUNSEL_HADIPUTRANTO',
            corpusRawContent: 'Email communication logs regarding concession boundaries.'
        );

        $this->assertNotNull($export->hash_verification_sha256);
        $this->assertEquals('EXTERNAL_COUNSEL_HADIPUTRANTO', $export->authorized_recipient_id);
        $this->assertTrue((bool) $export->attorney_client_privilege_filtered);
    }

    public function test_governance_retention_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->placeLegalHold('HOLD-AUD', 'Title', 'Entity', 'GC');
        $this->service->registerRecord('REC-AUD', 'CUSTOMER_PII', '2026-01-01');
        $this->service->executeRetentionDisposition('REC-AUD');
        $this->service->exportEdiscoveryCorpus('EXP-AUD', 'Scope', 'REC-1', 'Content');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: financial ledger record anonymized/disposed
        DB::table('gov_retention_records')->insert([
            'record_code' => 'REC-TAMPERED-LEDGER',
            'record_class' => 'FINANCIAL_LEDGER',
            'is_financial_ledger_immutable' => true,
            'retention_expiry_date' => '2030-01-01',
            'disposition_status' => 'ANONYMIZED', // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
