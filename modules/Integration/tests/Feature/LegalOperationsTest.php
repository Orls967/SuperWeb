<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\LegalOperationsService;
use Tests\TestCase;

/**
 * Fase 176 — Legal Operations, Disputes & Knowledge Management Tests
 *
 * Covers:
 *  (a) privileged documents inaccessible to non-counsel
 *  (b) evidence checksum verifies authenticity
 *  (c) dispute settlement posts once (idempotent)
 *  (d) legal:audit = 0 discrepancy
 */
class LegalOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected LegalOperationsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LegalOperationsService::class);
    }

    /**
     * (a) & (b) Evidence store with checksum & privilege access restriction.
     */
    public function test_evidence_bundle_and_privilege_access(): void
    {
        $matter = $this->service->createMatter('Patent Infringement Defense', 'STRICT_PRIVILEGED');
        $fileContent = 'CONFIDENTIAL_ATTORNEY_WORK_PRODUCT_LEGAL_ANALYSIS';
        $bundle = $this->service->storeEvidence($matter->matter_code, 'Memo Analysis', $fileContent);

        $this->assertSame(hash('sha256', $fileContent), $bundle->file_checksum_sha256);

        // 1. Non-counsel role (e.g. AUDITOR or SALES) -> Access denied
        try {
            $this->service->accessEvidence($bundle->bundle_code, 'AUDITOR');
            $this->fail('Expected exception for non-counsel access to privileged document.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('strictly restricted to LEGAL_COUNSEL', $e->getMessage());
        }

        // 2. Counsel role -> Access SUCCESS
        $accessed = $this->service->accessEvidence($bundle->bundle_code, 'LEGAL_COUNSEL');
        $this->assertSame($bundle->bundle_code, $accessed->bundle_code);
    }

    /**
     * (c) Dispute settlement posts once and is idempotent.
     */
    public function test_dispute_settlement_posting_idempotent(): void
    {
        $matter = $this->service->createMatter('Vendor Commercial Dispute', 'CONFIDENTIAL');

        // Initial settlement: Rp 500,000,000
        $settlement1 = $this->service->executeSettlement($matter->matter_code, 500000000.0);
        $this->assertTrue((bool) $settlement1->is_posted_to_ledger);
        $this->assertSame('SETTLED', $settlement1->status);

        // Re-execution returns same record without duplicate posting
        $settlement2 = $this->service->executeSettlement($matter->matter_code, 500000000.0);
        $this->assertSame($settlement1->settlement_code, $settlement2->settlement_code);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_legal_operations_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
