<?php

declare(strict_types=1);

namespace Modules\Wealth\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Wealth\Application\Services\RoboAdvisorService;
use Modules\Wealth\Domain\Models\WmHolding;
use Modules\Wealth\Domain\Models\WmOrder;
use Tests\TestCase;

class WealthTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->seed(LedgerAccountsSeeder::class);
        $wallet = $this->user->walletAccount('IDR');
        $wallet->forceFill(['cached_balance' => '50000000'])->save();
    }

    /**
     * DoR 73.7a: Alokasi tidak pernah menembus guardrail likuiditas minimum 2 bulan.
     */
    public function test_liquidity_guardrail_prevents_investing_emergency_fund(): void
    {
        /** @var RoboAdvisorService $service */
        $service = app(RoboAdvisorService::class);

        // Monthly spend baseline: 5,000,000 IDR => Emergency target 10,000,000 IDR (2 bulan)
        $profile = $service->configureProfile(
            userId: $this->user->id,
            riskProfile: 'moderate',
            monthlySpendBaselineIdr: 5000000
        );

        $this->assertSame(10000000, $profile->emergency_fund_target_idr);

        // Test detector: Saldo 9M < Safe Floor 10M => surplus 0
        $detection = $service->detectSurplus($profile, 9000000, 5000000);
        $this->assertSame(0, $detection['investable_surplus_idr']);
        $this->assertFalse($detection['guardrail_maintained']);

        // Saldo 9,000,000 IDR => executeSurplusAutoInvest returns 0
        $invested = $service->executeSurplusAutoInvest($profile, 9000000, '2026-10');
        $this->assertSame(0, $invested);
        $this->assertSame(0, (int) WmHolding::where('profile_id', $profile->id)->sum('value_idr'));
    }

    /**
     * DoR 73.7b: Order terdividasi = dana terpotong & wm_orders tercatat idempoten.
     */
    public function test_surplus_auto_invest_allocates_correctly_and_creates_orders(): void
    {
        /** @var RoboAdvisorService $service */
        $service = app(RoboAdvisorService::class);

        $profile = $service->configureProfile(
            userId: $this->user->id,
            riskProfile: 'moderate',
            monthlySpendBaselineIdr: 5000000,
            allocationPcts: [50, 30, 20]
        );

        // Saldo 20,000,000 IDR => Surplus = 20M - 10M = 10,000,000 IDR
        $invested = $service->executeSurplusAutoInvest($profile, 20000000, '2026-10');
        $this->assertSame(10000000, $invested);

        // Verifikasi orders di wm_orders
        $orders = WmOrder::where('profile_id', $profile->id)->get();
        $this->assertCount(3, $orders);

        $mfOrder = $orders->firstWhere('asset_type', 'mutual_fund');
        $goldOrder = $orders->firstWhere('asset_type', 'digital_gold');
        $cryptoOrder = $orders->firstWhere('asset_type', 'crypto');

        $this->assertSame(5000000, $mfOrder->amount_idr);
        $this->assertSame(3000000, $goldOrder->amount_idr);
        $this->assertSame(2000000, $cryptoOrder->amount_idr);
        $this->assertSame(10000000, (int) $orders->sum('amount_idr'));

        // Verifikasi holdings
        $this->assertSame(5000000, (int) WmHolding::where('profile_id', $profile->id)->where('asset_type', 'mutual_fund')->value('value_idr'));
        $this->assertSame(3000000, (int) WmHolding::where('profile_id', $profile->id)->where('asset_type', 'digital_gold')->value('value_idr'));
        $this->assertSame(2000000, (int) WmHolding::where('profile_id', $profile->id)->where('asset_type', 'crypto')->value('value_idr'));

        // Idempotency: re-eksekusi untuk siklus yang sama tidak membuat order duplikat
        $invested2 = $service->executeSurplusAutoInvest($profile, 20000000, '2026-10');
        $this->assertSame($invested, $invested2);
        $this->assertSame(3, WmOrder::where('profile_id', $profile->id)->count());
    }

    /**
     * DoR 73.7c: Rebalancing deterministik ketika drift > 5% target.
     */
    public function test_portfolio_rebalance_triggers_on_drift_exceeding_five_percent(): void
    {
        /** @var RoboAdvisorService $service */
        $service = app(RoboAdvisorService::class);

        $profile = $service->configureProfile(
            userId: $this->user->id,
            riskProfile: 'aggressive',
            monthlySpendBaselineIdr: 5000000,
            allocationPcts: [50, 30, 20]
        );

        // Invest awal 10,000,000 IDR (MF: 5M, Gold: 3M, Crypto: 2M)
        $service->executeSurplusAutoInvest($profile, 20000000, '2026-10');

        // Simulasi drift: Crypto melonjak drastis dari 2M menjadi 8M (Total portofolio = 5M + 3M + 8M = 16M)
        // Crypto sekarang = 8M / 16M = 50% (target 20% => drift 30% > 5%)
        $cryptoHolding = WmHolding::where('profile_id', $profile->id)->where('asset_type', 'crypto')->firstOrFail();
        $cryptoHolding->value_idr = 8000000;
        $cryptoHolding->save();

        // Eksekusi rebalancing
        $result = $service->rebalancePortfolio($profile);

        $this->assertTrue($result['drift_detected']);
        $this->assertTrue($result['rebalanced']);
        $this->assertGreaterThan(5, $result['max_drift_pct']);

        // Verifikasi total value tetap terjaga = 16,000,000 IDR
        $totalHoldingsAfter = (int) WmHolding::where('profile_id', $profile->id)->sum('value_idr');
        $this->assertSame(16000000, $totalHoldingsAfter);

        // Verifikasi alokasi kembali tepat 50% MF (8M), 30% Gold (4.8M), 20% Crypto (3.2M)
        $this->assertSame(8000000, (int) WmHolding::where('profile_id', $profile->id)->where('asset_type', 'mutual_fund')->value('value_idr'));
        $this->assertSame(4800000, (int) WmHolding::where('profile_id', $profile->id)->where('asset_type', 'digital_gold')->value('value_idr'));
        $this->assertSame(3200000, (int) WmHolding::where('profile_id', $profile->id)->where('asset_type', 'crypto')->value('value_idr'));

        // Jika dijalankan lagi tanpa perubahan, tidak ada rebalance
        $result2 = $service->rebalancePortfolio($profile);
        $this->assertFalse($result2['rebalanced']);
    }

    /**
     * DoR 73.7d: Fee akurat 6 desimal integer & accrual ke ledger.
     */
    public function test_management_fee_calculation_and_ledger_accrual_six_decimal_accuracy(): void
    {
        /** @var RoboAdvisorService $service */
        $service = app(RoboAdvisorService::class);

        // 100,000,000 IDR portofolio dengan 25 bps tahunan (0.25% p.a.)
        // Fee harian = (100,000,000 * 25) / (365 * 10000) = 2,500,000,000 / 3,650,000 = 684 IDR (pembagian integer presisi)
        $dailyFee = $service->calculateDailyManagementFee(100000000, 25);
        $this->assertSame(684, $dailyFee);

        $profile = $service->configureProfile(
            userId: $this->user->id,
            riskProfile: 'moderate',
            monthlySpendBaselineIdr: 5000000
        );

        $wallet = $this->user->walletAccount('IDR');
        $wallet->forceFill(['cached_balance' => '150000000'])->save();

        $service->executeSurplusAutoInvest($profile, 110000000, '2026-10'); // 100M surplus invest

        $accruedFee = $service->accrueManagementFee($profile, 25);
        $this->assertSame(684, $accruedFee);

        // Idempotency check: fee accrual untuk tanggal yang sama tidak mendebit ganda
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'wm_fee_accrual',
            'reference_type' => 'wm_profile',
            'reference_id' => (string) $profile->id,
        ]);
    }

    /**
     * DoR 73.7e: Reconcile holdings = total executed orders.
     */
    public function test_reconcile_wealth_holdings_equals_executed_orders(): void
    {
        /** @var RoboAdvisorService $service */
        $service = app(RoboAdvisorService::class);

        $profile = $service->configureProfile(
            userId: $this->user->id,
            riskProfile: 'moderate',
            monthlySpendBaselineIdr: 5000000
        );

        $service->executeSurplusAutoInvest($profile, 30000000, '2026-10'); // 20M invested

        $reconciliation = $service->reconcileHoldings($profile);
        $this->assertTrue($reconciliation['is_reconciled']);
        $this->assertSame(20000000, $reconciliation['total_holdings_idr']);
        $this->assertSame(20000000, $reconciliation['total_orders_idr']);

        // Injeksi anomali: ubah holding manual tanpa order
        $holding = WmHolding::where('profile_id', $profile->id)->firstOrFail();
        $holding->value_idr += 500000;
        $holding->save();

        $reconciliationCorrupted = $service->reconcileHoldings($profile);
        $this->assertFalse($reconciliationCorrupted['is_reconciled']);
    }
}
