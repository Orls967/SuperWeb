<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\TaxCustomsTradeService;
use Tests\TestCase;

/**
 * Fase 208 — Risiko: Tax, Customs & Trade Compliance 30 Lini Tests
 *
 * Covers:
 *  (a) consolidated tax calculation with gapless e-faktur numbering
 *  (b) TBML guard detects invoice vs customs value variance > 10% and holds transaction
 *  (c) trade within acceptable variance passes cleanly
 *  (d) trade:audit = 0 discrepancy
 */
class TaxCustomsTradeTest extends TestCase
{
    use RefreshDatabase;

    protected TaxCustomsTradeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TaxCustomsTradeService::class);
    }

    /**
     * (a) Tax calculation and gapless e-faktur sequence.
     */
    public function test_tax_calculation_and_gapless_efaktur(): void
    {
        // 11% PPN on 100,000,000 IDR -> 11,000,000 IDR tax
        $inv1 = $this->service->issueTaxInvoice('ID', 'PPN_11', 100000000.0, 11.0);
        $this->assertSame(1, (int) $inv1->tax_invoice_seq);
        $this->assertEquals(11000000.00, (float) $inv1->calculated_tax_amount_idr);
        $this->assertSame('FAKTUR-ID-2026-0000001', $inv1->efaktur_number);

        $inv2 = $this->service->issueTaxInvoice('ID', 'PPH_23', 50000000.0, 2.0);
        $this->assertSame(2, (int) $inv2->tax_invoice_seq);
        $this->assertEquals(1000000.00, (float) $inv2->calculated_tax_amount_idr);
        $this->assertSame('FAKTUR-ID-2026-0000002', $inv2->efaktur_number);
    }

    /**
     * (b) & (c) TBML trade valuation checks.
     */
    public function test_trade_tbml_valuation_checks(): void
    {
        // 1. Clean trade (Variance 2% <= 10%) -> CLEARED
        $trade1 = $this->service->inspectTradeTransaction('TRD-001', 'INV-EXPORT-881', 100000000.0, 102000000.0);
        $this->assertFalse((bool) $trade1->is_flagged_for_review);
        $this->assertSame('CLEARED', $trade1->status);

        // 2. High variance trade (Declared 100M vs Invoiced 140M = 40% > 10%) -> HELD_FOR_REVIEW
        $trade2 = $this->service->inspectTradeTransaction('TRD-002', 'INV-EXPORT-882', 100000000.0, 140000000.0);
        $this->assertTrue((bool) $trade2->is_flagged_for_review);
        $this->assertSame('HELD_FOR_REVIEW', $trade2->status);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies (when flagged trades cleared).
     */
    public function test_tax_trade_audit(): void
    {
        DB::table('erm_tbml_trade_guards')->truncate();
        $this->service->inspectTradeTransaction('TRD-CLEAN', 'INV-001', 1000000.0, 1000000.0);

        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
