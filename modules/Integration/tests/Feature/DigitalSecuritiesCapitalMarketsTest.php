<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DigitalSecuritiesCapitalMarketsService;
use Tests\TestCase;

class DigitalSecuritiesCapitalMarketsTest extends TestCase
{
    use RefreshDatabase;

    protected DigitalSecuritiesCapitalMarketsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DigitalSecuritiesCapitalMarketsService::class);
    }

    public function test_issuance_bookbuilding_and_allocation_sum_guard(): void
    {
        // 1. Create tokenized green bond issuance of 100,000 tokens (271.1)
        $this->service->createIssuance(
            issuanceCode: 'BOND-SOLAR-2026',
            tokenSymbol: 'SOLAR-DEBT',
            securityType: 'DEBT_BOND_TOKEN',
            totalTokens: 100000.0,
            faceValueUsd: 100.0
        );

        // 2. Onboard institutional investor (271.2)
        $this->service->onboardInvestor('INV-TEMASEK', 'INSTITUTIONAL', 'AGGRESSIVE');

        // 3. Allocate 60,000 tokens succeeds
        $issuance = $this->service->allocateTokens('BOND-SOLAR-2026', 'INV-TEMASEK', 60000.0);
        $this->assertEquals(60000.0, (float) $issuance->total_allocated_tokens);

        // 4. Over-allocation exceeding total issued (60,000 + 50,000 > 100,000) rejected (271.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds total issued');
        $this->service->allocateTokens('BOND-SOLAR-2026', 'INV-TEMASEK', 50000.0);
    }

    public function test_investor_suitability_rejection_and_downgrade_suspension(): void
    {
        $this->service->createIssuance('EQ-SMELTER-01', 'SMELT-EQ', 'EQUITY_TOKEN', 50000.0, 10.0);

        // 1. Conservative investor rejected by suitability gate for risk instrument (271.2 & 271.4)
        $this->service->onboardInvestor('INV-RETIREE', 'TIER_1', 'CONSERVATIVE');

        try {
            $this->service->allocateTokens('EQ-SMELTER-01', 'INV-RETIREE', 1000.0);
            $this->fail('Expected exception for conservative suitability');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('rejected by suitability gate', $e->getMessage());
        }

        // 2. Suitability downgrade suspends access (271.6 Edge Case)
        $this->service->onboardInvestor('INV-SPECULATOR', 'TIER_2', 'AGGRESSIVE');
        $suspended = $this->service->updateInvestorSuitability('INV-SPECULATOR', 'CONSERVATIVE', true);
        $this->assertTrue((bool) $suspended->is_access_suspended);
    }

    public function test_market_making_inventory_limit_and_thin_liquidity_spread(): void
    {
        // 1. Normal orderbook with deep liquidity ($100,000 depth) (271.3)
        $normalQuote = $this->service->updateMarketMakingQuote(
            marketSymbol: 'NICKEL-TOKEN-USDT',
            bidPrice: 100.0,
            askPrice: 101.5,
            currentInventoryUsd: 400000.0,
            maxInventoryLimitUsd: 1000000.0,
            orderbookDepthUsd: 100000.0
        );
        $this->assertFalse((bool) $normalQuote->is_liquidity_thin_warning);
        $this->assertEquals(1.50, (float) $normalQuote->spread_pct);

        // 2. Thin orderbook (< $25,000 depth) widens spread automatically with warning (271.5 Edge Case)
        $thinQuote = $this->service->updateMarketMakingQuote(
            marketSymbol: 'RARE-EARTH-TOKEN-USDT',
            bidPrice: 50.0,
            askPrice: 51.0,
            currentInventoryUsd: 100000.0,
            maxInventoryLimitUsd: 500000.0,
            orderbookDepthUsd: 12000.0 // Thin liquidity!
        );
        $this->assertTrue((bool) $thinQuote->is_liquidity_thin_warning);
        $this->assertGreaterThanOrEqual(8.50, (float) $thinQuote->spread_pct);

        // 3. Inventory limit breach rejected (271.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('inventory limit exceeded');
        $this->service->updateMarketMakingQuote('OVER-INV', 10.0, 11.0, 600000.0, 500000.0, 50000.0);
    }

    public function test_corporate_action_mandatory_notice_period(): void
    {
        // 1. Notice period < 14 days is strictly rejected (271.7)
        try {
            $this->service->scheduleCorporateAction(
                actionCode: 'CORP-COUPON-RUSH',
                issuanceCode: 'BOND-01',
                actionType: 'COUPON_PAYMENT',
                noticePeriodDays: 5, // Violation!
                executionDate: now()->addDays(5)->toDateString()
            );
            $this->fail('Expected exception for short notice period');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Minimum 14 days advance notice required', $e->getMessage());
        }

        // 2. Notice period of 21 days succeeds
        $action = $this->service->scheduleCorporateAction(
            actionCode: 'CORP-DIVIDEND-Q4',
            issuanceCode: 'EQ-01',
            actionType: 'DIVIDEND_PAYOUT',
            noticePeriodDays: 21,
            executionDate: now()->addDays(21)->toDateString()
        );
        $this->assertEquals(21, (int) $action->notice_period_days);
    }

    public function test_digital_securities_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->createIssuance('ISS-AUD', 'AUD', 'EQUITY_TOKEN', 1000.0, 10.0);
        $this->service->onboardInvestor('INV-AUD', 'INSTITUTIONAL', 'AGGRESSIVE');
        $this->service->allocateTokens('ISS-AUD', 'INV-AUD', 500.0);
        $this->service->updateMarketMakingQuote('AUD-MKT', 10.0, 10.5, 1000.0, 5000.0, 50000.0);
        $this->service->scheduleCorporateAction('ACT-AUD', 'ISS-AUD', 'DIVIDEND_PAYOUT', 15, now()->addDays(15)->toDateString());

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: market maker exceeding max inventory
        DB::table('digital_securities_market_making')->insert([
            'market_symbol' => 'ROGUE-MKT',
            'bid_price_usd' => 10.0,
            'ask_price_usd' => 11.0,
            'spread_pct' => 10.0,
            'current_inventory_usd' => 2000000.0,
            'max_inventory_limit_usd' => 500000.0, // Discrepancy: exceeded!
            'orderbook_depth_usd' => 10000.0,
            'is_liquidity_thin_warning' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
