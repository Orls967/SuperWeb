<?php

declare(strict_types=1);

namespace Tests\Acceptance\FinAsset\Eksplorasi;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Wealth\Application\Actions\ComputeInvestorRiskToleranceProfileAction;
use Modules\Wealth\Application\Actions\RebalancePortfolioDriftIdempotentAction;
use Modules\Wealth\Application\Actions\SweepCorporateIdleCashToOvernightYieldAction;
use Modules\Wealth\Domain\Models\WmHolding;
use Modules\Wealth\Domain\Models\WmOrder;
use Modules\Wealth\Domain\Models\WmPlan;
use Modules\Wealth\Domain\Models\WmProfile;
use Modules\Wealth\Ledger\WealthAccounts;
use Tests\TestCase;

/**
 * L4.0 Bukti Kondisi Kode Lama: Eksplorasi Modul Wealth Management (§2F Slice 3).
 *
 * Klasifikasi Action:
 * - ComputeInvestorRiskToleranceProfileAction: SEBAGIAN (0-100 score to 3 tiers, missing survey question weights, bps allocations, and drift bands)
 * - RebalancePortfolioDriftIdempotentAction: SEBAGIAN (percentage float math, direct value_idr mutation without units, NAV, or B0′.8 lot buying)
 * - SweepCorporateIdleCashToOvernightYieldAction: KERANGKA (manual cash parameter instead of ledger balance check, zero ledger postings, no event consumer)
 */
class WealthLegacyExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected User $investor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->investor = User::factory()->create();
        $this->investor->walletAccount('IDR');
    }

    public function test_legacy_tables_exist_and_missing_tables_identified(): void
    {
        expect(Schema::hasTable('wm_profiles'))->toBeTrue();
        expect(Schema::hasTable('wm_plans'))->toBeTrue();
        expect(Schema::hasTable('wm_holdings'))->toBeTrue();
        expect(Schema::hasTable('wm_orders'))->toBeTrue();

        // Extended tables required by KONSEP §2F and W-1..W-5
        expect(Schema::hasTable('wm_portfolio_holdings'))->toBeTrue(); // Unit-based NAV holdings
        expect(Schema::hasTable('wm_nav_history'))->toBeTrue();        // NAV oracle price history
        expect(Schema::hasTable('wm_sweep_configs'))->toBeTrue();       // Payroll & cash sweep rules
    }

    public function test_legacy_ledger_accounts_missing_custody_and_transit_accounts(): void
    {
        $definitions = WealthAccounts::definitions();
        $codes = array_column($definitions, 'code');

        expect($codes)->toContain('wm:management_fee:IDR');
        expect($codes)->not->toContain('wm:cash_sweep_clearing:IDR');
        expect($codes)->not->toContain('wm:portfolio_custody:default:IDR');
    }

    public function test_compute_risk_profile_action_status_sebagian(): void
    {
        $action = app(ComputeInvestorRiskToleranceProfileAction::class);
        $res = $action->handle(
            userId: $this->investor->id,
            monthlySpendBaselineIdr: 10_000_000,
            riskSurveyScore: 75,
            emergencyBufferMonths: 6
        );

        expect($res['profile'])->toBeInstanceOf(WmProfile::class);
        expect($res['risk_tier'])->toBe('aggressive');
        expect($res['emergency_fund_target_idr'])->toBe(60_000_000);

        // Weakness: Missing 5-tier integer scale (1..5), survey question weighting, and bps allocation models
        expect($res['profile']->risk_profile)->toBe('aggressive');
    }

    public function test_rebalance_portfolio_action_status_sebagian(): void
    {
        $profile = WmProfile::create([
            'user_id' => $this->investor->id,
            'risk_profile' => 'moderate',
            'emergency_fund_target_idr' => 10_000_000,
            'monthly_spend_baseline_idr' => 5_000_000,
            'auto_invest_enabled' => true,
        ]);

        WmPlan::create([
            'profile_id' => $profile->id,
            'allocation_mutual_funds_pct' => 50,
            'allocation_gold_pct' => 30,
            'allocation_crypto_pct' => 20,
        ]);

        WmHolding::create(['profile_id' => $profile->id, 'asset_type' => 'mutual_funds', 'value_idr' => 10_000_000]);
        WmHolding::create(['profile_id' => $profile->id, 'asset_type' => 'gold', 'value_idr' => 10_000_000]);
        WmHolding::create(['profile_id' => $profile->id, 'asset_type' => 'crypto', 'value_idr' => 0]);

        $action = app(RebalancePortfolioDriftIdempotentAction::class);
        $res = $action->handle($profile->id, driftThresholdPct: 5);

        expect($res['total_portfolio_idr'])->toBe(20_000_000);
        expect($res['rebalanced'])->toBeTrue();

        // Weakness: Modifies value_idr directly without unit tracking, NAV price evaluation, or B0′.8 lot purchasing
        $goldHolding = WmHolding::where('profile_id', $profile->id)->where('asset_type', 'gold')->first();
        expect($goldHolding->value_idr)->toBe(6_000_000);
    }

    public function test_sweep_corporate_cash_action_status_kerangka(): void
    {
        $profile = WmProfile::create([
            'user_id' => $this->investor->id,
            'risk_profile' => 'conservative',
            'emergency_fund_target_idr' => 10_000_000,
            'monthly_spend_baseline_idr' => 5_000_000,
            'auto_invest_enabled' => true,
        ]);

        $action = app(SweepCorporateIdleCashToOvernightYieldAction::class);
        $res = $action->handle(
            profileId: $profile->id,
            availableCashIdr: 50_000_000, // arbitrary input parameter rather than reading ledger balance
            operatingLiquidityBufferIdr: 20_000_000
        );

        expect($res['swept_amount_idr'])->toBe(30_000_000);
        expect($res['order'])->toBeInstanceOf(WmOrder::class);

        // Weakness: No ledger transfer posted from operational cash to money market investment!
        // No consumer for HCM PayrollDisbursed event.
    }
}
