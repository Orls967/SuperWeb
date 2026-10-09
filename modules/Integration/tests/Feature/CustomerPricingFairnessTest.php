<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CustomerPricingFairnessService;
use Tests\TestCase;

class CustomerPricingFairnessTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerPricingFairnessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CustomerPricingFairnessService::class);
    }

    public function test_fair_offer_with_transparency_breakdown_flow(): void
    {
        // 419.1 Register legal differentiation basis (volume purchase)
        $this->service->registerRule(
            ruleCode: 'RULE-VOL-DISC',
            differentiationBasis: 'volume_discount',
            isProhibited: false,
            maxDiscount: 25.00
        );

        // 419.2 & 419.3 Personalized offer with transparency components
        $offer = $this->service->generatePersonalizedOffer(
            offerCode: 'OFR-VOL-2026-001',
            customerId: 'CUST-CORP-88',
            basis: 'volume_discount',
            basePrice: 10000000.00,
            discountPercent: 20.00, // <= 25%
            priceComponents: [
                'base_unit_rate' => 10000000.00,
                'tier_volume_rebate' => -2000000.00,
                'service_charge' => 0.00,
            ]
        );

        $this->assertEquals('OFR-VOL-2026-001', $offer->offer_code);
        $this->assertEquals(8000000.00, (float) $offer->final_price);

        // 419.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_prohibited_basis_and_excessive_discount_blocked_edge_case(): void
    {
        // 419.1 & 419.5 Register legally prohibited discriminatory basis
        $this->service->registerRule(
            ruleCode: 'RULE-DISCRIMINATORY',
            differentiationBasis: 'protected_demographic',
            isProhibited: true
        );

        // Attempting to price based on prohibited basis is blocked
        try {
            $this->service->generatePersonalizedOffer(
                offerCode: 'OFR-ILLEGAL',
                customerId: 'CUST-100',
                basis: 'protected_demographic',
                basePrice: 500000.00,
                discountPercent: 10.00,
                priceComponents: ['base' => 500000]
            );
            $this->fail('Expected exception for prohibited pricing basis');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('legally prohibited / discriminatory', $e->getMessage());
        }

        // 419.2 Attempting discount exceeding max cap
        $this->service->registerRule('RULE-CAP', 'loyalty_promo', false, 15.00);

        try {
            $this->service->generatePersonalizedOffer(
                offerCode: 'OFR-EXCEED-CAP',
                customerId: 'CUST-101',
                basis: 'loyalty_promo',
                basePrice: 1000000.00,
                discountPercent: 40.00, // > 15% cap!
                priceComponents: ['base' => 1000000]
            );
            $this->fail('Expected exception for discount cap breach');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds maximum allowed cap', $e->getMessage());
        }
    }
}
