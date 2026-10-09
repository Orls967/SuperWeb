<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CashForecastingLiquidityCommandService;
use Tests\TestCase;

class CashForecastingLiquidityCommandTest extends TestCase
{
    use RefreshDatabase;

    protected CashForecastingLiquidityCommandService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CashForecastingLiquidityCommandService::class);
    }

    public function test_13_week_rolling_forecast_and_bias_correction(): void
    {
        // 1. Submit forecast (275.1)
        $this->service->submitRollingForecast(
            forecastCode: 'FC-CASH-Q4-2026',
            entityCode: 'ENT-NICKEL-MINING',
            predictedInflowUsd: 10000000.0,
            predictedOutflowUsd: 7000000.0
        );

        // 2. High variance miss triggers bias correction (275.1 & 275.5 Edge Case)
        // Predicted net: $3,000,000; Actual net: $500,000 => variance $2,500,000 => accuracy ~16.67% < 80%
        $reconciled = $this->service->reconcileForecastActuals(
            forecastCode: 'FC-CASH-Q4-2026',
            actualInflowUsd: 5500000.0,
            actualOutflowUsd: 5000000.0
        );

        $this->assertTrue((bool) $reconciled->bias_correction_required);
        $this->assertLessThan(80.0, (float) $reconciled->accuracy_pct);
    }

    public function test_intraday_cash_sweep_guards(): void
    {
        // 1. Fresh intraday balance updated (275.2 & 275.6)
        $this->service->updateIntradayPosition(
            accountCode: 'ACC-BCA-OPERATING-01',
            bankName: 'BCA',
            currentBalanceUsd: 1500000.0,
            projectedEodUsd: 1200000.0,
            syncedAt: now()
        );

        // 2. Valid sweep reduces balance (275.2)
        $swept = $this->service->executeAccountSweep('ACC-BCA-OPERATING-01', 500000.0);
        $this->assertEquals(1000000.0, (float) $swept->current_balance_usd);

        // 3. Negative balance sweep rejected (275.4)
        try {
            $this->service->executeAccountSweep('ACC-BCA-OPERATING-01', 1500000.0); // Only 1,000,000 left
            $this->fail('Expected exception for negative balance sweep');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds available balance', $e->getMessage());
        }

        // 4. Stale data sweep rejected (275.6 Edge Case)
        $this->service->updateIntradayPosition(
            accountCode: 'ACC-STALE-MANDIRI',
            bankName: 'MANDIRI',
            currentBalanceUsd: 500000.0,
            projectedEodUsd: 500000.0,
            syncedAt: Carbon::now()->subHours(2) // 2 hours old!
        );

        try {
            $this->service->executeAccountSweep('ACC-STALE-MANDIRI', 100000.0);
            $this->fail('Expected exception for stale balance sweep');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('is stale (> 60m old)', $e->getMessage());
        }
    }

    public function test_liquidity_stress_test_and_board_alert(): void
    {
        // 1. Normal survival scenario (> 30 days) (275.3 & 275.4)
        $safeScenario = $this->service->runLiquidityStressTest(
            testCode: 'STRESS-MODERATE',
            scenarioName: 'SUPPLIER_PRICE_SURGE',
            availableLiquidityUsd: 5000000.0,
            dailyBurnRateUsd: 50000.0 // 100 days survival
        );
        $this->assertEquals(100, (int) $safeScenario->survival_days);
        $this->assertFalse((bool) $safeScenario->board_alert_triggered);

        // 2. Severe stress scenario (< 30 days) triggers board alert (275.3 & 275.7)
        $crisisScenario = $this->service->runLiquidityStressTest(
            testCode: 'STRESS-MARKET-FREEZE',
            scenarioName: 'MARKET_FREEZE',
            availableLiquidityUsd: 2000000.0,
            dailyBurnRateUsd: 100000.0, // 20 days survival!
            preapprovedContingencyActive: true
        );
        $this->assertEquals(20, (int) $crisisScenario->survival_days);
        $this->assertTrue((bool) $crisisScenario->board_alert_triggered);
        $this->assertTrue((bool) $crisisScenario->preapproved_contingency_active);
    }

    public function test_cash_treasury_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->submitRollingForecast('FC-AUD', 'ENT-1', 1000.0, 500.0);
        $this->service->reconcileForecastActuals('FC-AUD', 980.0, 500.0);
        $this->service->updateIntradayPosition('ACC-AUD', 'BANK', 1000.0, 1000.0, now());
        $this->service->runLiquidityStressTest('TEST-AUD', 'SCEN', 1000.0, 10.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: negative balance on account
        DB::table('cash_intraday_positions')->insert([
            'account_code' => 'ACC-OVERDRAWN',
            'bank_name' => 'BANK_ROGUE',
            'current_balance_usd' => -50000.0, // Discrepancy!
            'projected_eod_balance_usd' => -50000.0,
            'last_synced_at' => now(),
            'is_stale' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
