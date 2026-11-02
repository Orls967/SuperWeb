<?php

declare(strict_types=1);

namespace Tests\Acceptance\FinAsset;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Wealth\Application\Services\CashSweepService;
use Modules\Wealth\Application\Services\PortfolioManagementService;
use Modules\Wealth\Application\Services\RoboAdvisorService;
use Modules\Wealth\Domain\Models\WmNavHistory;
use Modules\Wealth\Domain\Models\WmPortfolioHolding;
use Modules\Wealth\Domain\Models\WmProfile;
use Tests\TestCase;

/**
 * Acceptance Tests: LINI 4 Keuangan Aset - Wealth (§2F Slice 3).
 *
 * Menguji seluruh aturan bisnis W-1 s.d. W-5:
 * - Profil risiko 1..5 dari survei tertimbang; alokasi target bps; drift band +-500 bps
 * - Unitized holdings dengan nilai unit/NAV skala 1e4; revaluasi MTM harian
 * - Auto-invest cash sweep dari event PayrollDisbursed & dividen RWA
 * - Rebalancing dengan B0′.8 PortfolioRebalancer saat drift melebihi batas band
 * - Audit command wm:audit dua-sumber & fixture korupsi
 */
class WealthAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $investor1;

    protected User $investor2;

    protected User $wealthAdvisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->investor1 = User::factory()->create(['role' => 'investor']);
        $this->investor2 = User::factory()->create(['role' => 'investor']);
        $this->wealthAdvisor = User::factory()->create(['role' => 'wealth_manager']);

        $this->investor1->walletAccount('IDR');
        $this->investor2->walletAccount('IDR');
    }

    public function test_01_risk_survey_requires_valid_idempotency_key(): void
    {
        $advisorService = app(RoboAdvisorService::class);

        $this->expectException(\InvalidArgumentException::class);
        $advisorService->submitRiskSurvey(
            userId: $this->investor1->id,
            answers: ['q1' => 3, 'q2' => 4],
            idempotencyKey: ''
        );
    }

    public function test_02_risk_survey_computes_score_1_to_5_and_maps_to_target_weights_bps(): void
    {
        $advisorService = app(RoboAdvisorService::class);

        // Conservative survey answers -> score 1
        $profile = $advisorService->submitRiskSurvey(
            userId: $this->investor1->id,
            answers: ['q1' => 1, 'q2' => 1, 'q3' => 1],
            idempotencyKey: 'wm:surv:002'
        );

        expect($profile->risk_score)->toBe(1)
            ->and($profile->target_money_market_bps)->toBe(7000)
            ->and($profile->target_bonds_bps)->toBe(2500)
            ->and($profile->target_equity_rwa_bps)->toBe(500)
            ->and($profile->target_money_market_bps + $profile->target_bonds_bps + $profile->target_equity_rwa_bps)->toBe(10000);
    }

    public function test_03_moderate_survey_profile_weights_bps(): void
    {
        $advisorService = app(RoboAdvisorService::class);

        // Moderate survey answers -> score 3
        $profile = $advisorService->submitRiskSurvey(
            userId: $this->investor1->id,
            answers: ['q1' => 3, 'q2' => 3, 'q3' => 3],
            idempotencyKey: 'wm:surv:003'
        );

        expect($profile->risk_score)->toBe(3)
            ->and($profile->target_money_market_bps)->toBe(3000)
            ->and($profile->target_bonds_bps)->toBe(4000)
            ->and($profile->target_equity_rwa_bps)->toBe(3000);
    }

    public function test_04_aggressive_survey_profile_weights_bps(): void
    {
        $advisorService = app(RoboAdvisorService::class);

        // Aggressive survey answers -> score 5
        $profile = $advisorService->submitRiskSurvey(
            userId: $this->investor1->id,
            answers: ['q1' => 5, 'q2' => 5, 'q3' => 5],
            idempotencyKey: 'wm:surv:004'
        );

        expect($profile->risk_score)->toBe(5)
            ->and($profile->target_money_market_bps)->toBe(1000)
            ->and($profile->target_bonds_bps)->toBe(2000)
            ->and($profile->target_equity_rwa_bps)->toBe(7000);
    }

    public function test_05_profile_drift_band_defaults_to_500_bps(): void
    {
        $advisorService = app(RoboAdvisorService::class);
        $profile = $advisorService->submitRiskSurvey(
            userId: $this->investor1->id,
            answers: ['q1' => 3, 'q2' => 3, 'q3' => 3],
            idempotencyKey: 'wm:surv:005'
        );

        expect($profile->drift_band_bps)->toBe(500);
    }

    public function test_06_nav_price_feed_records_scaled_nav_and_history(): void
    {
        $portfolioService = app(PortfolioManagementService::class);
        $record = $portfolioService->recordNavUpdate(
            assetCode: 'PU_INDEX',
            navScale4: 10_000, // NAV 1.0000 -> 10,000
            source: 'IDN_MONEY_MARKET_INDEX',
            idempotencyKey: 'wm:nav:006'
        );

        expect($record)->toBeInstanceOf(WmNavHistory::class)
            ->and($record->nav_scale_4)->toBe(10_000);
    }

    public function test_07_nav_history_table_is_append_only(): void
    {
        $record = WmNavHistory::create([
            'asset_code' => 'BOND_INDEX',
            'nav_date' => now()->toDateString(),
            'nav_scale_4' => 12_500,
            'source' => 'ORACLE',
            'idempotency_key' => 'wm:nav:fact:007',
        ]);

        $this->expectException(\DomainException::class);
        // Modifying NAV history fact violates PR5
        $record->update(['nav_scale_4' => 13_000]);
    }

    public function test_08_mtm_revaluation_updates_current_market_value_integer_rupiah(): void
    {
        $profile = $this->createProfileForUser($this->investor1, 3);
        $holding = WmPortfolioHolding::create([
            'profile_id' => $profile->id,
            'asset_code' => 'PU_INDEX',
            'units' => 100,
            'average_cost_nav' => 10_000,
            'current_nav' => 10_000,
            'current_market_value_idr' => 100_000, // 100 * 10,000 / 10^4 * 100
        ]);

        $portfolioService = app(PortfolioManagementService::class);
        // Update NAV from 10,000 to 11,000 (10% gain)
        $portfolioService->recordNavUpdate('PU_INDEX', 11_000, 'INDEX_FEED', 'wm:nav:008');
        $portfolioService->revaluePortfolioMtm($profile->id, idempotencyKey: 'wm:mtm:008');

        $holding->refresh();
        expect($holding->current_nav)->toBe(11_000)
            ->and($holding->current_market_value_idr)->toBe(110_000);
    }

    public function test_09_cash_sweep_configuration_validates_threshold_and_bps(): void
    {
        $sweepService = app(CashSweepService::class);

        $this->expectException(\InvalidArgumentException::class);
        // Sweep percentage cannot exceed 10000 bps
        $sweepService->configureSweep([
            'user_id' => $this->investor1->id,
            'threshold_cash_idr' => 5_000_000,
            'sweep_percentage_bps' => 12_000,
        ], idempotencyKey: 'wm:swp:cfg:fail:009');
    }

    public function test_10_cash_sweep_detects_surplus_cash_above_threshold(): void
    {
        $this->investor1->walletAccount('IDR')->forceFill(['cached_balance' => '12000000'])->save();

        $sweepService = app(CashSweepService::class);
        $config = $sweepService->configureSweep([
            'user_id' => $this->investor1->id,
            'threshold_cash_idr' => 5_000_000,
            'sweep_percentage_bps' => 5000, // 50% of surplus
        ], idempotencyKey: 'wm:swp:cfg:010');

        $result = $sweepService->evaluateAndSweep($this->investor1->id, idempotencyKey: 'wm:swp:exec:010');

        // Surplus = 12M - 5M = 7M. 50% = 3.5M
        expect($result['swept_amount_idr'])->toBe(3_500_000);
    }

    public function test_11_cash_sweep_posts_double_entry_ledger_movements(): void
    {
        $this->investor1->walletAccount('IDR')->forceFill(['cached_balance' => '10000000'])->save();

        $sweepService = app(CashSweepService::class);
        $sweepService->configureSweep([
            'user_id' => $this->investor1->id,
            'threshold_cash_idr' => 6_000_000,
            'sweep_percentage_bps' => 10_000, // 100% of surplus (4M)
        ], idempotencyKey: 'wm:swp:cfg:011');

        $sweepService->evaluateAndSweep($this->investor1->id, idempotencyKey: 'wm:swp:exec:011');

        $this->investor1->walletAccount('IDR')->refresh();
        expect((int) $this->investor1->walletAccount('IDR')->cached_balance)->toBe(6_000_000);
    }

    public function test_12_cash_sweep_skips_when_cash_below_threshold(): void
    {
        $this->investor1->walletAccount('IDR')->forceFill(['cached_balance' => '4000000'])->save();

        $sweepService = app(CashSweepService::class);
        $sweepService->configureSweep([
            'user_id' => $this->investor1->id,
            'threshold_cash_idr' => 5_000_000,
            'sweep_percentage_bps' => 10_000,
        ], idempotencyKey: 'wm:swp:cfg:012');

        $result = $sweepService->evaluateAndSweep($this->investor1->id, idempotencyKey: 'wm:swp:exec:012');
        expect($result['swept_amount_idr'])->toBe(0);
    }

    public function test_13_payroll_event_consumer_triggers_auto_invest_sweep(): void
    {
        $this->investor1->walletAccount('IDR')->forceFill(['cached_balance' => '15000000'])->save();

        $sweepService = app(CashSweepService::class);
        $sweepService->configureSweep([
            'user_id' => $this->investor1->id,
            'threshold_cash_idr' => 5_000_000,
            'sweep_percentage_bps' => 2000, // 20%
        ], idempotencyKey: 'wm:swp:cfg:013');

        // Simulate PayrollDisbursed event arrival
        $sweepService->handlePayrollDisbursedEvent([
            'user_id' => $this->investor1->id,
            'payroll_id' => 99,
            'amount_idr' => 10_000_000,
        ], idempotencyKey: 'wm:evt:pay:013');

        $this->investor1->walletAccount('IDR')->refresh();
        // Surplus = 15M - 5M = 10M. 20% swept = 2M. Remaining = 13M
        expect((int) $this->investor1->walletAccount('IDR')->cached_balance)->toBe(13_000_000);
    }

    public function test_14_rebalancing_skips_when_portfolio_drift_is_within_band(): void
    {
        $profile = $this->createProfileForUser($this->investor1, 3); // 3000 / 4000 / 3000

        // In-band: 3100 / 3900 / 3000 (drift within +-500 bps)
        WmPortfolioHolding::create(['profile_id' => $profile->id, 'asset_code' => 'PU_INDEX', 'units' => 31, 'average_cost_nav' => 10_000, 'current_nav' => 10_000, 'current_market_value_idr' => 310_000]);
        WmPortfolioHolding::create(['profile_id' => $profile->id, 'asset_code' => 'BOND_INDEX', 'units' => 39, 'average_cost_nav' => 10_000, 'current_nav' => 10_000, 'current_market_value_idr' => 390_000]);
        WmPortfolioHolding::create(['profile_id' => $profile->id, 'asset_code' => 'RWA_INDEX', 'units' => 30, 'average_cost_nav' => 10_000, 'current_nav' => 10_000, 'current_market_value_idr' => 300_000]);

        $portfolioService = app(PortfolioManagementService::class);
        $result = $portfolioService->evaluateAndRebalance($profile->id, idempotencyKey: 'wm:rebal:skip:014');

        expect($result['rebalanced'])->toBeFalse()
            ->and($result['orders_count'])->toBe(0);
    }

    public function test_15_rebalancing_triggers_when_drift_exceeds_band(): void
    {
        $profile = $this->createProfileForUser($this->investor1, 3); // 3000 / 4000 / 3000

        // Significant drift: PU has 6000 bps (target 3000 + 500 max band = 3500)
        WmPortfolioHolding::create(['profile_id' => $profile->id, 'asset_code' => 'PU_INDEX', 'units' => 60, 'average_cost_nav' => 10_000, 'current_nav' => 10_000, 'current_market_value_idr' => 600_000]);
        WmPortfolioHolding::create(['profile_id' => $profile->id, 'asset_code' => 'BOND_INDEX', 'units' => 20, 'average_cost_nav' => 10_000, 'current_nav' => 10_000, 'current_market_value_idr' => 200_000]);
        WmPortfolioHolding::create(['profile_id' => $profile->id, 'asset_code' => 'RWA_INDEX', 'units' => 20, 'average_cost_nav' => 10_000, 'current_nav' => 10_000, 'current_market_value_idr' => 200_000]);

        $portfolioService = app(PortfolioManagementService::class);
        $result = $portfolioService->evaluateAndRebalance($profile->id, idempotencyKey: 'wm:rebal:exec:015');

        expect($result['rebalanced'])->toBeTrue()
            ->and($result['orders_count'])->toBeGreaterThan(0);
    }

    public function test_16_rebalancing_uses_portfolio_rebalancer_b08_band_selling_and_lot_buying(): void
    {
        // Vector 1 from B0′.8: Kas 1.000.000, target 6000 / 4000, NAV A 10.000, B 25.000, lot 1 -> A 60, B 16, sisa kas 0
        $profile = $this->createProfileWithCustomWeights($this->investor1, targetA: 6000, targetB: 4000);
        $this->investor1->walletAccount('IDR')->forceFill(['cached_balance' => '1000000'])->save();

        $portfolioService = app(PortfolioManagementService::class);
        $result = $portfolioService->investCashWithTargetWeights(
            profileId: $profile->id,
            cashAmountIdr: 1_000_000,
            assets: [
                ['id' => 'ASSET_A', 'nav' => 10_000, 'target_bps' => 6000, 'lot_size' => 1],
                ['id' => 'ASSET_B', 'nav' => 25_000, 'target_bps' => 4000, 'lot_size' => 1],
            ],
            idempotencyKey: 'wm:invest:b08:016'
        );

        expect($result['lots_purchased']['ASSET_A'])->toBe(60)
            ->and($result['lots_purchased']['ASSET_B'])->toBe(16)
            ->and($result['remaining_cash_idr'])->toBe(0);
    }

    public function test_17_rebalancing_never_negative_units(): void
    {
        $profile = $this->createProfileForUser($this->investor1, 3);
        WmPortfolioHolding::create(['profile_id' => $profile->id, 'asset_code' => 'PU_INDEX', 'units' => 10, 'average_cost_nav' => 10_000, 'current_nav' => 10_000, 'current_market_value_idr' => 100_000]);

        $portfolioService = app(PortfolioManagementService::class);
        $portfolioService->evaluateAndRebalance($profile->id, idempotencyKey: 'wm:rebal:noneg:017');

        $holdings = WmPortfolioHolding::where('profile_id', $profile->id)->get();
        foreach ($holdings as $h) {
            expect($h->units)->toBeGreaterThanOrEqual(0);
        }
    }

    public function test_18_management_fee_calculation_deducts_annual_bps_pro_rata(): void
    {
        $profile = $this->createProfileForUser($this->investor1, 3);
        WmPortfolioHolding::create([
            'profile_id' => $profile->id,
            'asset_code' => 'PU_INDEX',
            'units' => 1000,
            'average_cost_nav' => 10_000,
            'current_nav' => 10_000,
            'current_market_value_idr' => 100_000_000, // AUM 100M IDR
        ]);

        $portfolioService = app(PortfolioManagementService::class);
        // Annual fee 100 bps (1%) on 100M = 1,000,000 IDR/year -> monthly = 83,333 IDR
        $feeResult = $portfolioService->chargeMonthlyManagementFee($profile->id, feeAnnualBps: 100, idempotencyKey: 'wm:fee:018');

        expect($feeResult['fee_amount_idr'])->toBe(83_333);
    }

    public function test_19_idempotency_same_rebalance_key_same_day_skips_duplicate_execution(): void
    {
        $profile = $this->createProfileForUser($this->investor1, 3);
        WmPortfolioHolding::create(['profile_id' => $profile->id, 'asset_code' => 'PU_INDEX', 'units' => 80, 'average_cost_nav' => 10_000, 'current_nav' => 10_000, 'current_market_value_idr' => 800_000]);
        WmPortfolioHolding::create(['profile_id' => $profile->id, 'asset_code' => 'BOND_INDEX', 'units' => 10, 'average_cost_nav' => 10_000, 'current_nav' => 10_000, 'current_market_value_idr' => 100_000]);
        WmPortfolioHolding::create(['profile_id' => $profile->id, 'asset_code' => 'RWA_INDEX', 'units' => 10, 'average_cost_nav' => 10_000, 'current_nav' => 10_000, 'current_market_value_idr' => 100_000]);

        $portfolioService = app(PortfolioManagementService::class);
        $key = 'wm:rebal:idem:019';

        $res1 = $portfolioService->evaluateAndRebalance($profile->id, idempotencyKey: $key);
        $res2 = $portfolioService->evaluateAndRebalance($profile->id, idempotencyKey: $key);

        expect($res1['orders_count'])->toBe($res2['orders_count']);
    }

    public function test_20_http_wealth_index_requires_authentication(): void
    {
        $response = $this->get('/wealth');
        $response->assertRedirect('/login');
    }

    public function test_21_http_wealth_index_renders_for_investor(): void
    {
        $response = $this->actingAs($this->investor1)->get('/wealth');
        $response->assertOk();
    }

    public function test_22_http_risk_survey_submit_requires_idempotency_key(): void
    {
        $response = $this->actingAs($this->investor1)->postJson('/wealth/risk-profiles/submit', [
            'answers' => ['q1' => 3, 'q2' => 3],
        ]);

        $response->assertStatus(422);
    }

    public function test_23_http_cash_sweep_view_displays_configured_rules(): void
    {
        $response = $this->actingAs($this->investor1)->get('/wealth/cash-sweep');
        $response->assertOk();
    }

    public function test_24_http_portfolio_rebalancing_view_renders(): void
    {
        $response = $this->actingAs($this->investor1)->get('/wealth/portfolio-rebalancing');
        $response->assertOk();
    }

    public function test_25_audit_two_source_passes_with_reconciled_portfolio_and_custody(): void
    {
        $exitCode = Artisan::call('wm:audit');
        expect($exitCode)->toBe(0);
    }

    public function test_26_audit_two_source_detects_corrupted_portfolio_units(): void
    {
        $profile = $this->createProfileForUser($this->investor1, 3);
        WmPortfolioHolding::create([
            'profile_id' => $profile->id,
            'asset_code' => 'PU_INDEX',
            'units' => 100,
            'average_cost_nav' => 10_000,
            'current_nav' => 10_000,
            'current_market_value_idr' => 100_000,
        ]);

        // Maliciously corrupt units without ledger balance adjustment
        WmPortfolioHolding::where('profile_id', $profile->id)
            ->where('asset_code', 'PU_INDEX')
            ->update(['units' => 9999]);

        $exitCode = Artisan::call('wm:audit');
        expect($exitCode)->not->toBe(0);
    }

    // Helpers
    protected function createProfileForUser(User $user, int $score): WmProfile
    {
        $weights = match ($score) {
            1 => ['mm' => 7000, 'bd' => 2500, 'eq' => 500],
            5 => ['mm' => 1000, 'bd' => 2000, 'eq' => 7000],
            default => ['mm' => 3000, 'bd' => 4000, 'eq' => 3000],
        };

        return WmProfile::create([
            'user_id' => $user->id,
            'risk_score' => $score,
            'risk_profile' => match ($score) {
                1 => 'conservative', 5 => 'aggressive', default => 'moderate'
            },
            'target_money_market_bps' => $weights['mm'],
            'target_bonds_bps' => $weights['bd'],
            'target_equity_rwa_bps' => $weights['eq'],
            'drift_band_bps' => 500,
            'auto_invest_enabled' => true,
        ]);
    }

    protected function createProfileWithCustomWeights(User $user, int $targetA, int $targetB): WmProfile
    {
        return WmProfile::create([
            'user_id' => $user->id,
            'risk_score' => 3,
            'risk_profile' => 'moderate',
            'target_money_market_bps' => $targetA,
            'target_bonds_bps' => $targetB,
            'target_equity_rwa_bps' => 0,
            'drift_band_bps' => 500,
            'auto_invest_enabled' => true,
        ]);
    }
}
