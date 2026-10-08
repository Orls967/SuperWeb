<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\FashionSourcingService;
use Tests\TestCase;

/**
 * Fase 181 — Apparel, Textile & Fashion Sourcing Tests
 *
 * Covers:
 *  (a) size-color SKU allocation exact (conservation)
 *  (b) unapproved/unethical factory blocked from PO issuance
 *  (c) markdown promotional plan respects margin guardrail
 *  (d) fashion:audit = 0 discrepancy
 */
class FashionSourcingTest extends TestCase
{
    use RefreshDatabase;

    protected FashionSourcingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FashionSourcingService::class);
    }

    /**
     * (a) & (b) PO issuance requires approved factory and exact SKU breakdown.
     */
    public function test_po_issuance_and_sku_allocation(): void
    {
        // 1. Factory failing labor audit -> BLOCKED
        $this->service->registerFactory('FAC-BDG-01', 'Bandung Textile Mills', false, true);
        try {
            $this->service->issuePurchaseOrder('FAC-BDG-01', 'Summer 2027', 100, [
                ['sku' => 'TSHIRT-BLK-M', 'color' => 'BLACK', 'size' => 'M', 'units' => 100],
            ]);
            $this->fail('Expected exception for factory failing labor standards.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('fails ethical labor or ESG standards', $e->getMessage());
        }

        // 2. Approved factory
        $this->service->registerFactory('FAC-SOLO-02', 'Solo Garment Eco', true, true);

        // Discrepancy in SKU sum: total is 100, but allocation sum is 80 -> Exception
        try {
            $this->service->issuePurchaseOrder('FAC-SOLO-02', 'Summer 2027', 100, [
                ['sku' => 'TSHIRT-BLK-S', 'color' => 'BLACK', 'size' => 'S', 'units' => 50],
                ['sku' => 'TSHIRT-BLK-M', 'color' => 'BLACK', 'size' => 'M', 'units' => 30],
            ]);
            $this->fail('Expected exception for SKU allocation discrepancy.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Sum of SKU breakdown units (80) does not equal total PO units (100)', $e->getMessage());
        }

        // Exact match: 50 + 50 = 100 -> SUCCESS
        $po = $this->service->issuePurchaseOrder('FAC-SOLO-02', 'Summer 2027', 100, [
            ['sku' => 'TSHIRT-BLK-S', 'color' => 'BLACK', 'size' => 'S', 'units' => 50],
            ['sku' => 'TSHIRT-BLK-M', 'color' => 'BLACK', 'size' => 'M', 'units' => 50],
        ]);
        $this->assertSame('ISSUED', $po->status);
    }

    /**
     * (c) Markdown respects minimum margin guardrails.
     */
    public function test_markdown_margin_guardrail(): void
    {
        // 1. Safe markdown: Rp 500,000 with 30% discount = Rp 350,000. Unit cost Rp 200,000 -> Margin APPROVED
        $m1 = $this->service->planMarkdown('Batik Premium', 500000.0, 30.0, 200000.0);
        $this->assertTrue((bool) $m1->margin_approved);

        // 2. Ruinous markdown: Rp 500,000 with 70% discount = Rp 150,000. Unit cost Rp 200,000 -> Margin REJECTED
        $m2 = $this->service->planMarkdown('Batik Premium', 500000.0, 70.0, 200000.0);
        $this->assertFalse((bool) $m2->margin_approved);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_fashion_sourcing_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
