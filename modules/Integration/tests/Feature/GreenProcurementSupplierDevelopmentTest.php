<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\GreenProcurementSupplierDevelopmentService;
use Tests\TestCase;

class GreenProcurementSupplierDevelopmentTest extends TestCase
{
    use RefreshDatabase;

    protected GreenProcurementSupplierDevelopmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GreenProcurementSupplierDevelopmentService::class);
    }

    public function test_supplier_green_evaluation_discount_and_contract_clause(): void
    {
        // 447.1 Evaluate supplier with verified footprint data
        $supp = $this->service->evaluateSupplier(
            supplierCode: 'VND-RENEW-PACK-01',
            supplierName: 'PT Bio Packaging Lestari',
            sustainabilityScore: 88.50,
            hasVerifiedData: true
        );

        $this->assertEquals('tier_1_green', $supp->risk_tier);
        $this->assertTrue((bool) $supp->has_verified_footprint_data);

        // 447.3 Apply green discount
        $discounted = $this->service->applyGreenDiscountPremium('VND-RENEW-PACK-01', 3.50);
        $this->assertEquals(3.50, (float) $discounted->green_discount_premium_percent);

        // 447.2 Agree on contractual decarbonization clause
        $contracted = $this->service->handleDecarbonizationNegotiation('VND-RENEW-PACK-01', true);
        $this->assertTrue((bool) $contracted->decarbonization_clause_agreed);

        // 447.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_greenwashing_discount_blocked_and_phased_downgrade_edge_cases(): void
    {
        // 447.6 Risk: Unverified supplier claiming green discount is blocked
        $this->service->evaluateSupplier('VND-UNVERIFIED', 'Unverified Vendor', 85.00, false);

        try {
            $this->service->applyGreenDiscountPremium('VND-UNVERIFIED', 5.00);
            $this->fail('Expected exception for unverified green discount');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Greenwashing blocked: Green discount/premium requires third-party verified', $e->getMessage());
        }

        // 447.5 Edge case: Refusing decarbonization target triggers phased tier downgrade
        $tier1 = $this->service->evaluateSupplier('VND-STUBBORN', 'Stubborn Logistics', 82.00, true);
        $this->assertEquals('tier_1_green', $tier1->risk_tier);

        // Refuses target -> downgraded to tier_2_neutral, not blacklisted
        $downgraded = $this->service->handleDecarbonizationNegotiation('VND-STUBBORN', false);
        $this->assertEquals('tier_2_neutral', $downgraded->risk_tier);
        $this->assertFalse((bool) $downgraded->decarbonization_clause_agreed);
    }
}
