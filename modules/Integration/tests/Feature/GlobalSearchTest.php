<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\GlobalSearchService;
use Tests\TestCase;

/**
 * Fase 194 — Skala: Search, Discovery & Global Navigation Tests
 *
 * Covers:
 *  (a) search results strictly bounded by tenant scope (anti-IDOR)
 *  (b) search results bounded by user role (privilege separation)
 *  (c) PII is masked in document previews
 *  (d) search:audit = 0 discrepancy
 */
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected GlobalSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GlobalSearchService::class);
    }

    /**
     * (a) & (b) Multi-tenant scope & role isolation in search queries.
     */
    public function test_search_scope_and_role_isolation(): void
    {
        // 1. Index Confidential Legal Contract for TENANT_A with role LEGAL
        $this->service->indexEntity(
            'CONTRACT',
            'CTR-A-999',
            'L25_LEGAL',
            'TENANT_A',
            'LEGAL',
            'perjanjian merger akuisisi rahasia',
            'Kontrak akuisisi PT Maju oleh PT Makmur senilai 50 Miliar'
        );

        // 2. Index Public Catalog item for ALL tenants
        $this->service->indexEntity(
            'PRODUCT',
            'SKU-BATIK-01',
            'L28_FASHION',
            'PUBLIC',
            'ALL',
            'kain batik sutra tulis premium',
            'Koleksi kain batik sutra premium'
        );

        // Scenario A: Legal officer of TENANT_A searches 'akuisisi' -> SUCCESS (sees confidential contract)
        $resultsLegalA = $this->service->search('akuisisi', 'TENANT_A', 'LEGAL');
        $this->assertCount(1, $resultsLegalA);

        // Scenario B: Staff of TENANT_B searches 'akuisisi' -> EMPTY (isolated across tenant)
        $resultsB = $this->service->search('akuisisi', 'TENANT_B', 'LEGAL');
        $this->assertCount(0, $resultsB);

        // Scenario C: Non-legal staff of TENANT_A searches 'akuisisi' -> EMPTY (role separation)
        $resultsStaffA = $this->service->search('akuisisi', 'TENANT_A', 'STAFF');
        $this->assertCount(0, $resultsStaffA);

        // Scenario D: Staff of TENANT_B searches public product 'batik' -> SUCCESS
        $resultsPublic = $this->service->search('batik', 'TENANT_B', 'STAFF');
        $this->assertCount(1, $resultsPublic);
    }

    /**
     * (c) Automated PII masking in search previews.
     */
    public function test_search_preview_masks_pii(): void
    {
        $entity = $this->service->indexEntity(
            'INVOICE',
            'INV-HOSP-101',
            'L18_HEALTHCARE',
            'TENANT_A',
            'FINANCE',
            'tagihan rawat inap pasien budi',
            'Kontak pasien budi@hospital.id dengan telepon +628123456789'
        );

        $this->assertStringNotContainsString('budi@hospital.id', $entity->sanitized_preview);
        $this->assertStringNotContainsString('+628123456789', $entity->sanitized_preview);
        $this->assertStringContainsString('[REDACTED_EMAIL]', $entity->sanitized_preview);
        $this->assertStringContainsString('[REDACTED_PHONE]', $entity->sanitized_preview);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_global_search_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
