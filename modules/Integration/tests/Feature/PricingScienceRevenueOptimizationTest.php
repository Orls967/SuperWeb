<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PricingScienceRevenueOptimizationService;
use Tests\TestCase;

class PricingScienceRevenueOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected PricingScienceRevenueOptimizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PricingScienceRevenueOptimizationService::class);
    }

    public function test_pricing_policy_floor_and_ceiling_guardrails(): void
    {
        $policy = $this->service->createPricingPolicy(
            businessLine: 'HEALTHCARE',
            pricingModel: 'VALUE_BASED',
            priceFloor: 100000.0,
            priceCeiling: 500000.0,
            minMarginPct: 20.0,
            circuitBreakerDropPct: 25.0
        );

        $this->assertEquals(100000.0, (float) $policy->price_floor);
        $this->assertEquals(500000.0, (float) $policy->price_ceiling);

        // 1. Valid proposal within bounds (245.3 & 245.5)
        $validProp = $this->service->proposePriceChange((int) $policy->id, 250000.0, 240000.0);
        $this->assertEquals('PENDING', $validProp->status);

        // 2. Proposal breaching price floor throws exception
        $this->expectException(InvalidArgumentException::class);
        $this->service->proposePriceChange((int) $policy->id, 50000.0, 240000.0);
    }

    public function test_circuit_breaker_trips_on_abrupt_price_crash(): void
    {
        $policy = $this->service->createPricingPolicy(
            businessLine: 'HOTEL',
            pricingModel: 'DYNAMIC',
            priceFloor: 500000.0,
            priceCeiling: 3000000.0,
            circuitBreakerDropPct: 25.0
        );

        // Crash from 2,000,000 to 1,200,000 (40% drop > 25% threshold) (245.6 Edge Case)
        $trippedProp = $this->service->proposePriceChange((int) $policy->id, 1200000.0, 2000000.0);

        $this->assertTrue((bool) $trippedProp->is_circuit_breaker_tripped);
        $this->assertEquals('TRIPPED', $trippedProp->status);

        // Normal manager cannot approve tripped circuit breaker
        try {
            $this->service->approvePriceChange((int) $trippedProp->id, 'SHIFT_MANAGER');
            $this->fail('Expected exception for non-VP approval of tripped circuit breaker');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires VP_COMMERCIAL approval', $e->getMessage());
        }

        // VP Commercial approves
        $approved = $this->service->approvePriceChange((int) $trippedProp->id, 'VP_COMMERCIAL_OFFICER');
        $this->assertEquals('APPROVED', $approved->status);
    }

    public function test_mass_price_change_requires_14_day_notice(): void
    {
        $policy = $this->service->createPricingPolicy(
            businessLine: 'TELECOM',
            pricingModel: 'PUBLIC_TARIFF',
            priceFloor: 50000.0,
            priceCeiling: 200000.0
        );

        // Mass change without 14-day notice rejected (245.7)
        try {
            $this->service->proposePriceChange((int) $policy->id, 90000.0, 80000.0, true, 7);
            $this->fail('Expected exception for insufficient notice');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('minimum of 14 days notice period', $e->getMessage());
        }

        // Mass change with 14-day notice passes
        $massProp = $this->service->proposePriceChange((int) $policy->id, 90000.0, 80000.0, true, 14);
        $this->assertTrue((bool) $massProp->is_mass_change);
        $this->assertEquals(14, (int) $massProp->notice_days);
    }

    public function test_elasticity_deterministic_ladder_and_revenue_lift(): void
    {
        $curve = $this->service->calculateElasticityPriceLadder(
            segment: 'ENTERPRISE',
            currentPrice: 1000.0,
            elasticityCoefficient: -0.650 // Inelastic -> price increase potential
        );

        $this->assertEquals('ENTERPRISE', $curve['customer_segment']);
        $this->assertEquals(1150.0, $curve['optimal_price']); // 15% markup
        $this->assertGreaterThan(0, $curve['projected_revenue_lift_pct']);
    }

    public function test_profit_pool_math_conservation(): void
    {
        // Line x Segment x Channel
        $this->service->recordProfitPool('ENERGY', 'COMMERCIAL', 'DIRECT', 1000000.0, 600000.0, 'GROW');
        $this->service->recordProfitPool('ENERGY', 'RESIDENTIAL', 'ONLINE', 500000.0, 350000.0, 'HOLD');
        $this->service->recordProfitPool('MINING', 'GLOBAL_EXPORTER', 'AGENT', 2500000.0, 1800000.0, 'HARVEST');

        $summary = $this->service->getProfitPoolSummary();

        $this->assertEquals(3, $summary['total_pools']);
        $this->assertEquals(4000000.0, $summary['total_revenue']);
        $this->assertEquals(2750000.0, $summary['total_cogs']);
        $this->assertEquals(1250000.0, $summary['summed_net_profit']);
        $this->assertEquals(1250000.0, $summary['calculated_net_profit']);
        $this->assertTrue($summary['is_profit_conserved']); // 245.5 Conservation rule
    }

    public function test_pricing_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $pol = $this->service->createPricingPolicy('AGRI', 'COST_PLUS', 10.0, 100.0);
        $prop = $this->service->proposePriceChange((int) $pol->id, 50.0, 45.0);
        $this->service->approvePriceChange((int) $prop->id, 'LEAD');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: tripped proposal approved by non-VP
        DB::table('pricing_change_proposals')->insert([
            'proposal_code' => 'PCHG-UNAUTHORIZED',
            'policy_rule_id' => $pol->id,
            'proposed_price' => 50.0,
            'previous_price' => 90.0,
            'is_circuit_breaker_tripped' => true,
            'is_mass_change' => false,
            'notice_days' => 0,
            'status' => 'APPROVED',
            'approved_by' => 'JUNIOR_ANALYST', // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
