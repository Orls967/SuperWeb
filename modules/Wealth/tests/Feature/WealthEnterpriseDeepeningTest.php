<?php

declare(strict_types=1);

namespace Modules\Wealth\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Wealth\Application\Actions\AllocateTargetMptPortfolioWeightsAction;
use Modules\Wealth\Application\Actions\ComputeInvestorRiskToleranceProfileAction;
use Modules\Wealth\Application\Actions\RebalancePortfolioDriftIdempotentAction;
use Modules\Wealth\Application\Actions\SweepCorporateIdleCashToOvernightYieldAction;
use Modules\Wealth\Domain\Models\WmHolding;
use Modules\Wealth\Domain\Models\WmPlan;
use Modules\Wealth\Domain\Models\WmProfile;
use Tests\TestCase;

class WealthEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_compute_investor_risk_tolerance_profile_determines_risk_tier_and_emergency_buffer(): void
    {
        $user = User::factory()->create();
        $action = app(ComputeInvestorRiskToleranceProfileAction::class);

        // Score 75 => aggressive, 15,000,000 baseline * 6 months = 90,000,000 IDR
        $result = $action->handle($user->id, 15000000, 75, 6);

        $this->assertEquals('aggressive', $result['risk_tier']);
        $this->assertEquals(90000000, $result['emergency_fund_target_idr']);
        $this->assertEquals('aggressive', $result['profile']->risk_profile);
    }

    public function test_allocate_target_mpt_portfolio_weights_sums_to_100_percent(): void
    {
        $user = User::factory()->create();
        $profile = WmProfile::create([
            'user_id' => $user->id,
            'risk_profile' => 'moderate',
            'emergency_fund_target_idr' => 60000000,
            'monthly_spend_baseline_idr' => 10000000,
            'auto_invest_enabled' => true,
        ]);

        $action = app(AllocateTargetMptPortfolioWeightsAction::class);
        $result = $action->handle($profile->id);

        $this->assertEquals(60, $result['mutual_funds_pct']);
        $this->assertEquals(30, $result['gold_pct']);
        $this->assertEquals(10, $result['crypto_pct']);
        $this->assertEquals(100, $result['mutual_funds_pct'] + $result['gold_pct'] + $result['crypto_pct']);
    }

    public function test_rebalance_portfolio_drift_executes_idempotent_rebalancing_orders(): void
    {
        $user = User::factory()->create();
        $profile = WmProfile::create([
            'user_id' => $user->id,
            'risk_profile' => 'moderate',
            'emergency_fund_target_idr' => 60000000,
            'monthly_spend_baseline_idr' => 10000000,
            'auto_invest_enabled' => true,
        ]);

        WmPlan::create([
            'profile_id' => $profile->id,
            'allocation_mutual_funds_pct' => 60,
            'allocation_gold_pct' => 30,
            'allocation_crypto_pct' => 10,
        ]);

        // Total 100M: Crypto drifted to 40M (40%), Mutual funds only 30M (30%), Gold 30M (30%)
        WmHolding::create(['profile_id' => $profile->id, 'asset_type' => 'mutual_funds', 'value_idr' => 30000000]);
        WmHolding::create(['profile_id' => $profile->id, 'asset_type' => 'gold', 'value_idr' => 30000000]);
        WmHolding::create(['profile_id' => $profile->id, 'asset_type' => 'crypto', 'value_idr' => 40000000]);

        $action = app(RebalancePortfolioDriftIdempotentAction::class);
        $result = $action->handle($profile->id, 5);

        $this->assertTrue($result['rebalanced']);
        $this->assertGreaterThan(0, $result['orders_count']);

        // Holdings must now match target weights: 60M mutual funds, 30M gold, 10M crypto
        $this->assertEquals(60000000, WmHolding::where('profile_id', $profile->id)->where('asset_type', 'mutual_funds')->first()->value_idr);
        $this->assertEquals(30000000, WmHolding::where('profile_id', $profile->id)->where('asset_type', 'gold')->first()->value_idr);
        $this->assertEquals(10000000, WmHolding::where('profile_id', $profile->id)->where('asset_type', 'crypto')->first()->value_idr);
    }

    public function test_rebalance_skips_when_portfolio_within_drift_threshold(): void
    {
        $user = User::factory()->create();
        $profile = WmProfile::create([
            'user_id' => $user->id,
            'risk_profile' => 'conservative',
            'emergency_fund_target_idr' => 60000000,
            'monthly_spend_baseline_idr' => 10000000,
            'auto_invest_enabled' => true,
        ]);

        WmPlan::create([
            'profile_id' => $profile->id,
            'allocation_mutual_funds_pct' => 80,
            'allocation_gold_pct' => 20,
            'allocation_crypto_pct' => 0,
        ]);

        // Exactly matching targets
        WmHolding::create(['profile_id' => $profile->id, 'asset_type' => 'mutual_funds', 'value_idr' => 80000000]);
        WmHolding::create(['profile_id' => $profile->id, 'asset_type' => 'gold', 'value_idr' => 20000000]);
        WmHolding::create(['profile_id' => $profile->id, 'asset_type' => 'crypto', 'value_idr' => 0]);

        $action = app(RebalancePortfolioDriftIdempotentAction::class);
        $result = $action->handle($profile->id, 5);

        $this->assertFalse($result['rebalanced']);
        $this->assertEquals(0, $result['orders_count']);
    }

    public function test_sweep_corporate_idle_cash_to_overnight_yield_credits_mutual_funds(): void
    {
        $user = User::factory()->create();
        $profile = WmProfile::create([
            'user_id' => $user->id,
            'risk_profile' => 'conservative',
            'emergency_fund_target_idr' => 20000000,
            'monthly_spend_baseline_idr' => 5000000,
            'auto_invest_enabled' => true,
        ]);

        $action = app(SweepCorporateIdleCashToOvernightYieldAction::class);

        // Available cash: 50M IDR, Buffer: 20M IDR => Swept 30M IDR
        $result = $action->handle($profile->id, 50000000, 20000000);

        $this->assertEquals(30000000, $result['swept_amount_idr']);
        $this->assertEquals(30000000, $result['new_holding_value_idr']);
        $this->assertEquals('executed', $result['order']->status);
    }

    public function test_wealth_treasury_http_routes_render_and_handle_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('wealth.treasury.risk-profiles'));
        $response->assertStatus(200);
        $response->assertSee('Wealth Risk Profiling');

        $response = $this->actingAs($admin)->get(route('wealth.treasury.mpt-allocation'));
        $response->assertStatus(200);
        $response->assertSee('Modern Portfolio Theory (MPT)');

        $response = $this->actingAs($admin)->get(route('wealth.treasury.portfolio-rebalancing'));
        $response->assertStatus(200);
        $response->assertSee('Automated Portfolio Rebalancing');

        $response = $this->actingAs($admin)->get(route('wealth.treasury.cash-sweep'));
        $response->assertStatus(200);
        $response->assertSee('Corporate Idle Cash Sweep');
    }
}
