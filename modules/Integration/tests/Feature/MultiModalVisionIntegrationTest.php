<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\MultiModalVisionIntegrationService;
use Tests\TestCase;

class MultiModalVisionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected MultiModalVisionIntegrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MultiModalVisionIntegrationService::class);
    }

    public function test_financial_document_extraction_and_ledger_posting_edge_case(): void
    {
        // 1. Posting to financial ledger without human verification throws exception (351.5 Edge Case)
        try {
            $this->service->extractAndPostFinancialDocument(
                extractionCode: 'EXT-INVOICE-SUPPLIER-01',
                docType: 'INVOICE',
                sourceUrl: 'https://storage.internal/invoices/inv-001.pdf',
                extractedAmountUsd: 154000.0,
                humanVerified: false, // Unverified!
                postToLedger: true // Attempting to post!
            );
            $this->fail('Expected exception for unverified financial document ledger post');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Extracted document fields must be human-verified before posting to financial ledger', $e->getMessage());
        }

        // 2. Verified document posts to ledger successfully (351.1 & 351.4)
        $doc = $this->service->extractAndPostFinancialDocument(
            extractionCode: 'EXT-INVOICE-SUPPLIER-02',
            docType: 'INVOICE',
            sourceUrl: 'https://storage.internal/invoices/inv-002.pdf',
            extractedAmountUsd: 154000.0,
            humanVerified: true,
            postToLedger: true
        );
        $this->assertTrue((bool) $doc->human_verified_material_fields);
        $this->assertTrue((bool) $doc->posted_to_financial_ledger);
    }

    public function test_vision_inspection_low_confidence_human_confirmation_guard(): void
    {
        // 1. Low confidence detection (< 0.85) without human confirmation throws exception (351.2 & 351.4)
        try {
            $this->service->executeVisionInspectionAction(
                inspectionCode: 'VIS-PPE-HAUL-ROAD-01',
                inspectionType: 'PPE_COMPLIANCE',
                confidenceScore: 0.6500, // < 0.85 threshold!
                humanConfirmed: false,
                threshold: 0.8500
            );
            $this->fail('Expected exception for low confidence without human confirmation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Low confidence detection (0.65 < 0.85) requires human confirmation', $e->getMessage());
        }

        // 2. Low confidence with human confirmation succeeds (351.2)
        $confirmed = $this->service->executeVisionInspectionAction(
            inspectionCode: 'VIS-PPE-HAUL-ROAD-02',
            inspectionType: 'PPE_COMPLIANCE',
            confidenceScore: 0.6500,
            humanConfirmed: true,
            threshold: 0.8500
        );
        $this->assertTrue((bool) $confirmed->human_confirmation_obtained);
        $this->assertTrue((bool) $confirmed->consequential_action_taken);

        // 3. High confidence succeeds directly (351.2)
        $highConf = $this->service->executeVisionInspectionAction(
            inspectionCode: 'VIS-QC-SMELTER-INGOT',
            inspectionType: 'QC_VISUAL_DEFECT',
            confidenceScore: 0.9850,
            humanConfirmed: false,
            threshold: 0.8500
        );
        $this->assertTrue((bool) $highConf->consequential_action_taken);
    }

    public function test_ai_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->extractAndPostFinancialDocument('E-AUD', 'INVOICE', 'http://url', 100.0, true, true);
        $this->service->executeVisionInspectionAction('I-AUD', 'PPE', 0.95, false, 0.85);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: ledger posted without human verification
        DB::table('multimodal_financial_document_extractions')->insert([
            'extraction_code' => 'E-DEFECT-UNVERIFIED',
            'document_type' => 'INVOICE',
            'source_document_url' => 'http://url',
            'extracted_amount_usd' => 5000.0,
            'human_verified_material_fields' => false, // Discrepancy!
            'posted_to_financial_ledger' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
