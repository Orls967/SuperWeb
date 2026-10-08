<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CpqOrderToCashService;
use Tests\TestCase;

class CpqOrderToCashTest extends TestCase
{
    use RefreshDatabase;

    protected CpqOrderToCashService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CpqOrderToCashService::class);
    }

    public function test_cpq_configuration_validation_and_quote_creation(): void
    {
        // 1. Valid configuration with EPC and mandatory Safety Insurance (249.1 & 249.5)
        $quote = $this->service->createAndValidateQuote(
            clientId: 'CLIENT-CORP-ALPHA',
            items: [
                ['type' => 'EPC_HEAVY', 'price' => 5000000.0],
                ['type' => 'SAFETY_INSURANCE', 'price' => 250000.0],
            ],
            validDays: 30
        );

        $this->assertEquals(5250000.0, (float) $quote->total_quoted_amount);
        $this->assertEquals('APPROVED', $quote->status);

        // 2. Invalid configuration: EPC_HEAVY without SAFETY_INSURANCE is rejected
        $this->expectException(InvalidArgumentException::class);
        $this->service->createAndValidateQuote(
            clientId: 'CLIENT-CORP-BETA',
            items: [
                ['type' => 'EPC_HEAVY', 'price' => 5000000.0],
            ]
        );
    }

    public function test_quote_conversion_credit_check_and_expiry_enforcement(): void
    {
        $quote = $this->service->createAndValidateQuote(
            clientId: 'CLIENT-OK',
            items: [['type' => 'SOFTWARE_SaaS', 'price' => 50000.0]],
            validDays: 14
        );

        // 1. Valid conversion with passing credit score (249.2 & 249.3)
        $order = $this->service->convertQuoteToOrder((int) $quote->id, 75.0);
        $this->assertEquals('ACCEPTED', $order->status);
        $this->assertEquals(50000.0, (float) $order->order_amount);

        // 2. Conversion on expired quote rejected (249.2 & 249.5)
        $expiredQuote = $this->service->createAndValidateQuote(
            clientId: 'CLIENT-EXPIRED',
            items: [['type' => 'SOFTWARE_SaaS', 'price' => 10000.0]]
        );
        DB::table('cpq_quotes')
            ->where('id', $expiredQuote->id)
            ->update(['expires_at' => now()->subDay()]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->convertQuoteToOrder((int) $expiredQuote->id, 80.0);
    }

    public function test_default_escalation_blocks_future_orders(): void
    {
        $quote = $this->service->createAndValidateQuote('CLIENT-DEFAULTING', [['type' => 'TELCO', 'price' => 20000.0]]);
        $order = $this->service->convertQuoteToOrder((int) $quote->id, 70.0);

        // Client fails payment -> escalated to collection and blocked (249.6 Edge Case)
        $escalated = $this->service->escalateOrderDefault((int) $order->id, 'Delinquent beyond 90 days');
        $this->assertEquals('ESCALATED_COLLECTION', $escalated->status);
        $this->assertEquals('DEFAULTED_BLOCKED', $escalated->credit_status);

        // Attempting new order for blocked client must be rejected
        $quote2 = $this->service->createAndValidateQuote('CLIENT-DEFAULTING', [['type' => 'TELCO', 'price' => 15000.0]]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->convertQuoteToOrder((int) $quote2->id, 70.0, true);
    }

    public function test_fulfillment_invoice_and_full_cash_collection(): void
    {
        $quote = $this->service->createAndValidateQuote('CLIENT-PAYING', [['type' => 'SERVICES', 'price' => 80000.0]]);
        $order = $this->service->convertQuoteToOrder((int) $quote->id, 85.0);

        // Fulfillment with delivery evidence (249.3)
        $fulfilled = $this->service->fulfillOrder((int) $order->id, 'DOC-BAST-SIGNED-2026-99.PDF');
        $this->assertEquals('FULFILLED', $fulfilled->status);
        $this->assertEquals('DOC-BAST-SIGNED-2026-99.PDF', $fulfilled->delivery_evidence_doc);

        // Invoice & Full Cash Collection (249.3 & 249.5)
        $invoice = $this->service->issueInvoiceAndCollectCash((int) $order->id, 80000.0);
        $this->assertEquals(80000.0, (float) $invoice->invoice_amount);
        $this->assertEquals(80000.0, (float) $invoice->cash_collected_amount);
        $this->assertTrue((bool) $invoice->is_fully_paid);
    }

    public function test_revenue_recognition_schedule_reconciliation(): void
    {
        $quote = $this->service->createAndValidateQuote('CLIENT-IFRS', [['type' => 'ANNUAL_LICENSE', 'price' => 120000.0]]);
        $order = $this->service->convertQuoteToOrder((int) $quote->id, 90.0);

        // Over time deferred revenue schedule (IFRS 15) (249.4 & 249.7)
        $sched = $this->service->createRevenueSchedule((int) $order->id, 'OVER_TIME_MONTHLY', 12);

        $this->assertEquals('OVER_TIME_MONTHLY', $sched->recognition_pattern);
        $this->assertEquals(120000.0, (float) $sched->deferred_revenue_amount);
        $this->assertEquals(0.0, (float) $sched->recognized_revenue_amount);
        $this->assertEquals(120000.0, (float) $sched->total_contract_value);
    }

    public function test_cpq_order_to_cash_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $quote = $this->service->createAndValidateQuote('CLIENT-AUD', [['type' => 'PROD', 'price' => 1000.0]]);
        $order = $this->service->convertQuoteToOrder((int) $quote->id, 80.0);
        $this->service->fulfillOrder((int) $order->id, 'PROOF.PDF');
        $this->service->issueInvoiceAndCollectCash((int) $order->id, 1000.0);
        $this->service->createRevenueSchedule((int) $order->id, 'POINT_IN_TIME', 1);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: invoice marked fully paid but cash collected < invoice amount
        DB::table('cpq_invoices_and_cash')->insert([
            'invoice_code' => 'INV-FALSE-PAID',
            'order_id' => 9999,
            'invoice_amount' => 50000.0,
            'cash_collected_amount' => 10000.0, // Incomplete!
            'is_fully_paid' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
