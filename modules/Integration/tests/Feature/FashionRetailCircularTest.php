<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\FashionRetailCircularService;
use Tests\TestCase;

/**
 * Fase 182 — Fashion Retail, Personalization & Circular Textiles Tests
 *
 * Covers:
 *  (a) return/exchange inventory replenishment reconciles correctly
 *  (b) made-to-measure measurement privacy consent strictly enforced
 *  (c) take-back customer loyalty credit issued once
 *  (d) fashion:audit = 0 discrepancy
 */
class FashionRetailCircularTest extends TestCase
{
    use RefreshDatabase;

    protected FashionRetailCircularService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FashionRetailCircularService::class);
    }

    /**
     * (a) Return/exchange stock replenishment reconciles inventory.
     */
    public function test_return_exchange_inventory_replenishment(): void
    {
        // Initial return of 5 units of TSHIRT-BLK-M to Grand Indonesia store
        $inv1 = $this->service->processReturnExchange('TSHIRT-BLK-M', 'STORE-GI-01', 5);
        $this->assertSame(5, (int) $inv1->stock_on_hand);

        // Additional return of 3 units
        $inv2 = $this->service->processReturnExchange('TSHIRT-BLK-M', 'STORE-GI-01', 3);
        $this->assertSame(8, (int) $inv2->stock_on_hand);
    }

    /**
     * (b) Custom made-to-measure requires measurement privacy consent.
     */
    public function test_custom_tailoring_requires_consent(): void
    {
        // 1. Without consent -> BLOCKED
        try {
            $this->service->orderCustomTailoring(6001, 'Slim-fit Wool Tuxedo', false);
            $this->fail('Expected exception for unconsented measurement processing.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Customer measurement data consent is mandatory', $e->getMessage());
        }

        // 2. With consent -> SUCCESS
        $order = $this->service->orderCustomTailoring(6001, 'Slim-fit Wool Tuxedo', true);
        $this->assertSame('IN_PRODUCTION', $order->status);
        $this->assertTrue((bool) $order->measurement_consent_granted);
    }

    /**
     * (c) Textile take-back issues single loyalty credit.
     */
    public function test_textile_takeback_credit_issuance(): void
    {
        $takeback = $this->service->issueTakebackCredit(6001, 'Denim Jacket', 'RECYCLE', 50000.0); // Rp 50,000 credit

        $this->assertTrue((bool) $takeback->credit_issued);
        $this->assertEquals(50000.00, (float) $takeback->loyalty_credit_idr);
        $this->assertSame('RECYCLE', $takeback->grading);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_fashion_retail_circular_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
