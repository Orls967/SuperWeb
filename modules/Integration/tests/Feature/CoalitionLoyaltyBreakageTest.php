<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\CoalitionLoyaltyBreakageService;
use Tests\TestCase;

class CoalitionLoyaltyBreakageTest extends TestCase
{
    use RefreshDatabase;

    protected CoalitionLoyaltyBreakageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CoalitionLoyaltyBreakageService::class);
    }

    public function test_points_credit_and_liability_ledger_reconciliation(): void
    {
        // 1. Credit 10,000 points @ $0.01 = $100.00 liability (283.1 & 283.4)
        $ledger = $this->service->creditLoyaltyPoints('ACC-GARUDA-MEMBER-01', 10000.0, 0.0100);
        $this->assertEquals(10000.0, (float) $ledger->points_balance);
        $this->assertEquals(100.00, (float) $ledger->total_liability_usd);

        // 2. Additional 5,000 points increases balance and liability
        $updated = $this->service->creditLoyaltyPoints('ACC-GARUDA-MEMBER-01', 5000.0);
        $this->assertEquals(15000.0, (float) $updated->points_balance);
        $this->assertEquals(150.00, (float) $updated->total_liability_usd);
    }

    public function test_point_devaluation_grandfathering_prevents_retroactive_loss(): void
    {
        // 1. Existing balance: 20,000 points @ $0.01 = $200 liability (283.5)
        $this->service->creditLoyaltyPoints('ACC-HOTEL-VIP', 20000.0, 0.0100);

        // 2. Devaluation to $0.0075 with grandfathering: existing balance keeps $0.0100 rate (283.5 Edge Case)
        $grandfathered = $this->service->applyPointDevaluation('ACC-HOTEL-VIP', 0.0075, grandfatherExistingBalance: true);
        $this->assertTrue((bool) $grandfathered->is_grandfathered_rate);
        $this->assertEquals(0.0100, (float) $grandfathered->point_valuation_usd);
        $this->assertEquals(200.00, (float) $grandfathered->total_liability_usd);
    }

    public function test_coalition_partner_settlement_default_and_reserve(): void
    {
        // 1. Register airline partner with $50,000 default reserve (283.1 & 283.6)
        $this->service->registerPartner('PARTNER-AIRLINE-X', 'AIRLINE', 2.5, 50000.0);

        // 2. Partner fails to pay $20,000 interchange -> reserve absorbed & escalation active (283.6 Edge Case)
        $defaulted = $this->service->handlePartnerSettlementDefault('PARTNER-AIRLINE-X', 20000.0);
        $this->assertEquals(20000.0, (float) $defaulted->outstanding_settlement_usd);
        $this->assertEquals(30000.0, (float) $defaulted->default_reserve_usd); // 50k - 20k
        $this->assertTrue((bool) $defaulted->is_in_default_escalation);
    }

    public function test_breakage_economics_forecast_and_reversal(): void
    {
        // 1. Recognize conservative breakage: 15% forecast redemption, $10,000 breakage revenue (283.2 & 283.7)
        $initialBreakage = $this->service->recognizeBreakage(
            recognitionCode: 'BRK-2026-Q3-01',
            periodName: '2026-Q3',
            forecastRedemptionPct: 15.0,
            breakageRevenueUsd: 10000.0
        );
        $this->assertEquals(0.0, (float) $initialBreakage->reversal_adjustment_usd);

        // 2. Actual redemptions higher than forecast (25% vs 15% => 10% diff) -> $1,000 reversal adjustment (283.2)
        $adjusted = $this->service->recognizeBreakage(
            recognitionCode: 'BRK-2026-Q3-RECON',
            periodName: '2026-Q3',
            forecastRedemptionPct: 15.0,
            breakageRevenueUsd: 10000.0,
            actualRedemptionPct: 25.0
        );
        $this->assertEquals(1000.0, (float) $adjusted->reversal_adjustment_usd);
    }

    public function test_coalition_loyalty_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerPartner('PTR-AUD', 'RETAIL', 1.5, 1000.0);
        $this->service->creditLoyaltyPoints('ACC-AUD', 100.0, 0.01);
        $this->service->recognizeBreakage('BRK-AUD', '2026-Q1', 10.0, 100.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unescalated partner default
        DB::table('coalition_loyalty_partners')->insert([
            'partner_code' => 'PTR-ROGUE-DEFAULT',
            'industry_sector' => 'HOTEL',
            'interchange_fee_rate_pct' => 2.0,
            'outstanding_settlement_usd' => 15000.0, // Default!
            'default_reserve_usd' => 0.0,
            'is_in_default_escalation' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
