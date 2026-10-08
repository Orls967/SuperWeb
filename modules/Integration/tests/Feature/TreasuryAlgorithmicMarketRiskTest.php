<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\TreasuryAlgorithmicMarketRiskService;
use Tests\TestCase;

class TreasuryAlgorithmicMarketRiskTest extends TestCase
{
    use RefreshDatabase;

    protected TreasuryAlgorithmicMarketRiskService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TreasuryAlgorithmicMarketRiskService::class);
    }

    public function test_var_market_risk_monitoring_and_emergency_hedge_authorization(): void
    {
        // 1. VaR within desk limit ($45,000 <= $100,000) (309.1 & 309.4)
        $deskNormal = $this->service->monitorDeskMarketRisk(
            deskCode: 'DESK-FX-SPOT-01',
            assetClass: 'FX_USD_IDR',
            grossExposureUsd: 25000000.0,
            var99Usd: 45000.0,
            varLimitUsd: 100000.0
        );
        $this->assertFalse((bool) $deskNormal->is_var_breached);
        $this->assertFalse((bool) $deskNormal->emergency_hedge_authorized);

        // 2. Sudden market shock causes VaR breach ($140,000 > $100,000) (309.1 & 309.4)
        $deskBreached = $this->service->monitorDeskMarketRisk(
            deskCode: 'DESK-FX-SPOT-01',
            assetClass: 'FX_USD_IDR',
            grossExposureUsd: 25000000.0,
            var99Usd: 140000.0,
            varLimitUsd: 100000.0
        );
        $this->assertTrue((bool) $deskBreached->is_var_breached);

        // 3. Emergency hedge authorized following breach (309.5 Edge Case)
        $hedged = $this->service->authorizeEmergencyHedge('DESK-FX-SPOT-01', 'HEAD_OF_TREASURY_ANITA');
        $this->assertTrue((bool) $hedged->emergency_hedge_authorized);
    }

    public function test_counterparty_credit_limit_enforcement(): void
    {
        // 1. Exposure within counterparty limit ($15,000,000 <= $20,000,000) (309.3 & 309.4)
        $approved = $this->service->evaluateCounterpartyLimit(
            counterpartyCode: 'CP-MANDIRI-01',
            institutionName: 'BANK_MANDIRI',
            proposedExposureUsd: 15000000.0,
            maxCreditLimitUsd: 20000000.0
        );
        $this->assertFalse((bool) $approved->limit_breach_detected);

        // 2. Limit breach detected before transaction execution triggers rejection (309.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Counterparty limit breach: Exposure');
        $this->service->evaluateCounterpartyLimit('CP-MANDIRI-01', 'BANK_MANDIRI', 25000000.0, 20000000.0);
    }

    public function test_treasury_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->monitorDeskMarketRisk('DESK-AUD', 'ASSET', 1000.0, 50.0, 100.0);
        $this->service->evaluateCounterpartyLimit('CP-AUD', 'BANK', 500.0, 1000.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unhedged breached VaR desk
        DB::table('treasury_market_risk_positions')->insert([
            'desk_code' => 'DESK-UNHEDGED-BREACH',
            'asset_class' => 'RATES',
            'gross_exposure_usd' => 5000000.0,
            'var_99_1d_usd' => 200000.0,
            'var_risk_limit_usd' => 100000.0,
            'is_var_breached' => true,
            'emergency_hedge_authorized' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
