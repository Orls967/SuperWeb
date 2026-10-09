<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DocumentRecordAutomationService;
use Tests\TestCase;

class DocumentRecordAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected DocumentRecordAutomationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DocumentRecordAutomationService::class);
    }

    public function test_low_confidence_financial_extraction_human_verification_edge_case(): void
    {
        // 1. Low confidence on financial invoice without human verification fails auto-post (368.2, 368.4, 368.5 Edge Case)
        try {
            $this->service->processExtraction(
                documentCode: 'DOC-INV-VENDOR-991',
                documentType: 'FINANCIAL_INVOICE',
                confidence: 0.8200, // 82% < 95% threshold!
                minThreshold: 0.9500,
                humanVerified: false // Not verified!
            );
            $this->fail('Expected exception for unverified low-confidence financial document extraction');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires human verification', $e->getMessage());
        }

        // Verify blocked extraction recorded
        $blocked = DB::table('platform_document_extractions')->where('document_code', 'DOC-INV-VENDOR-991')->first();
        $this->assertNotNull($blocked);
        $this->assertFalse((bool) $blocked->auto_posting_permitted);

        // 2. Low confidence with human verification succeeds and permits posting (368.2 & 368.5)
        $verified = $this->service->processExtraction(
            documentCode: 'DOC-INV-VENDOR-992',
            documentType: 'FINANCIAL_INVOICE',
            confidence: 0.8200,
            minThreshold: 0.9500,
            humanVerified: true // Human verified!
        );
        $this->assertTrue((bool) $verified->auto_posting_permitted);
        $this->assertTrue((bool) $verified->human_verified);

        // 3. High confidence succeeds directly (368.4)
        $highConfidence = $this->service->processExtraction(
            documentCode: 'DOC-INV-VENDOR-993',
            documentType: 'FINANCIAL_INVOICE',
            confidence: 0.9850,
            minThreshold: 0.9500,
            humanVerified: false
        );
        $this->assertTrue((bool) $highConfidence->auto_posting_permitted);
    }

    public function test_legal_hold_prevents_disposal_risk(): void
    {
        // Place legal hold on document (368.3 & 368.4)
        $hold = $this->service->placeLegalHold(
            holdCode: 'HOLD-LITIGATION-CASE-11',
            documentCode: 'DOC-SAFETY-LOG-MINE-01'
        );
        $this->assertTrue((bool) $hold->is_legal_hold_active);
        $this->assertTrue((bool) $hold->deletion_prevented);

        // Attempting to delete document under legal hold fails (368.4 & 368.6 Risk)
        try {
            $this->service->attemptDocumentDisposal('DOC-SAFETY-LOG-MINE-01');
            $this->fail('Expected exception for deleting document under legal hold');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('is under active legal hold and cannot be deleted', $e->getMessage());
        }
    }

    public function test_document_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->processExtraction('D-AUD', 'REPORT', 0.99, 0.95, false);
        $this->service->placeLegalHold('H-AUD', 'D-AUD');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: low-confidence financial document auto-posted without verification
        DB::table('platform_document_extractions')->insert([
            'document_code' => 'D-DEFECT-AUTOPOST',
            'document_type' => 'FINANCIAL_INVOICE',
            'extraction_confidence' => 0.5000,
            'min_confidence_threshold' => 0.9500,
            'human_verified' => false, // Discrepancy!
            'auto_posting_permitted' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
