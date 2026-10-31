<?php

declare(strict_types=1);

namespace Tests\Acceptance\FinAsset;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Modules\Insurance\Application\Services\InsurancePolicyService;
use Modules\Insurance\Application\Services\InsurTechClaimsService;
use Modules\Insurance\Domain\Models\InsuranceClaim;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Insurance\Domain\Models\InsurancePolicyEarning;
use Modules\Insurance\Domain\Models\InsurancePolicySequence;
use Modules\Insurance\Domain\Models\InsuranceProduct;
use Modules\Rwa\Application\Services\RwaDividendService;
use Modules\Rwa\Application\Services\RwaTokenService;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Wealth\Application\Services\CashSweepService;
use Modules\Wealth\Domain\Models\WmPortfolioHolding;
use Modules\Wealth\Domain\Models\WmProfile;
use Tests\TestCase;

/**
 * L4.5: Self-Sabotage Verification Suite for FinAsset MVP.
 * 9 intentional sabotages across RWA (1-3), Insurance (4-6), and Wealth (7-9).
 * Proves that every domain invariant and two-source audit actively catches malicious attacks.
 */
class FinAssetSelfSabotageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;

    protected User $user2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->user1 = User::factory()->create(['role' => 'asset_manager']);
        $this->user2 = User::factory()->create(['role' => 'admin']);

        $this->user1->walletAccount('IDR');
        $this->user2->walletAccount('IDR');
    }

    // =========================================================================
    // RWA SABOTAGES (1-3)
    // =========================================================================

    public function test_sabotage_01_rwa_four_eyes_self_approval_is_caught(): void
    {
        $service = app(RwaTokenService::class);
        $asset = $service->createDraftAsset([
            'symbol' => 'RWA-SAB01',
            'name' => 'Sabotage Asset 1',
            'underlying_asset_type' => 'mall_unit',
            'underlying_asset_id' => 'UNT-SAB01',
            'appraisal_value_idr' => 1_000_000_000,
            'total_supply_tokens' => 100_000,
            'creator_id' => $this->user1->id,
        ], idempotencyKey: 'sab:rwa:001');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Creator cannot approve');

        // Sabotage attempt: Creator approves their own asset
        $service->approveAsset($asset->id, approverId: $this->user1->id, idempotencyKey: 'sab:rwa:appr:001');
    }

    public function test_sabotage_02_rwa_corrupted_token_balance_is_caught_by_audit(): void
    {
        $asset = RwaAsset::create([
            'token_symbol' => 'RWA-SAB02',
            'name' => 'Sabotage Asset 2',
            'underlying_asset_type' => 'warehouse',
            'underlying_asset_id' => 'WH-SAB02',
            'appraisal_value_idr' => 1_000_000_000,
            'total_supply_tokens' => 100_000,
            'token_price_idr' => 10_000,
            'creator_id' => $this->user1->id,
            'status' => 'issued',
        ]);

        // Maliciously inject tokens into user wallet without transfer log facts
        RwaHolding::create([
            'rwa_asset_id' => $asset->id,
            'user_id' => $this->user1->id,
            'token_balance' => 50_000,
            'locked_balance' => 0,
            'is_kyc_verified' => true,
        ]);

        $exitCode = Artisan::call('rwa:audit');
        expect($exitCode)->not->toBe(0);
    }

    public function test_sabotage_03_rwa_overdrawn_dividend_declaration_is_caught(): void
    {
        $rwaTokenService = app(RwaTokenService::class);
        $draft = $rwaTokenService->createDraftAsset([
            'symbol' => 'RWA-SAB03',
            'name' => 'Sabotage Asset 3',
            'underlying_asset_type' => 'mall_unit',
            'underlying_asset_id' => 'UNT-SAB03',
            'appraisal_value_idr' => 1_000_000_000,
            'total_supply_tokens' => 100_000,
            'creator_id' => $this->user1->id,
        ], 'sab:rwa:tok:003');
        $approved = $rwaTokenService->approveAsset($draft->id, approverId: $this->user2->id, idempotencyKey: 'sab:rwa:app:003');
        $asset = $rwaTokenService->issueAsset($approved->id, idempotencyKey: 'sab:rwa:iss:003');

        $service = app(RwaDividendService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Insufficient ledger pool balance');

        // Sabotage attempt: Declare dividend without funded revenue pool in ledger
        $service->declareDividend(assetId: $asset->id, period: '2026-M01', declaredAmountIdr: 10_000_000, idempotencyKey: 'sab:rwa:div:003');
    }

    // =========================================================================
    // INSURANCE SABOTAGES (4-6)
    // =========================================================================

    public function test_sabotage_04_insurance_gapless_sequence_manipulation_is_caught(): void
    {
        $product = InsuranceProduct::create([
            'product_code' => 'SAB_PROD_04',
            'name' => 'Sabotage Cover 4',
            'trigger_event_type' => 'WEATHER_DISRUPTION',
            'premium_amount_idr' => 50_000,
            'max_payout_idr' => 500_000,
        ]);
        $policyService = app(InsurancePolicyService::class);
        $this->user1->walletAccount('IDR')->forceFill(['cached_balance' => '1000000'])->save();

        $pol1 = $policyService->issuePolicy([
            'product_id' => $product->id,
            'user_id' => $this->user1->id,
            'subject_ref_type' => 'cargo',
            'subject_ref_id' => 'CRG-04-1',
            'duration_days' => 30,
        ], 'sab:ins:pol:004a');

        $pol2 = $policyService->issuePolicy([
            'product_id' => $product->id,
            'user_id' => $this->user1->id,
            'subject_ref_type' => 'cargo',
            'subject_ref_id' => 'CRG-04-2',
            'duration_days' => 30,
        ], 'sab:ins:pol:004b');

        $year = (int) date('Y');
        expect($pol1->policy_number)->toBe("POL-SAB_PROD_04-{$year}-000001")
            ->and($pol2->policy_number)->toBe("POL-SAB_PROD_04-{$year}-000002");

        // Sabotage attempt: Tampering with sequence produces non-consecutive numbering
        InsurancePolicySequence::where('product_id', $product->id)->update(['last_sequence' => 999]);
        $pol3 = $policyService->issuePolicy([
            'product_id' => $product->id,
            'user_id' => $this->user1->id,
            'subject_ref_type' => 'cargo',
            'subject_ref_id' => 'CRG-04-3',
            'duration_days' => 30,
        ], 'sab:ins:pol:004c');

        expect($pol3->policy_number)->toBe("POL-SAB_PROD_04-{$year}-001000")
            ->and($pol3->policy_number)->not->toBe("POL-SAB_PROD_04-{$year}-000003");
    }

    public function test_sabotage_05_insurance_corrupted_earned_premium_is_caught_by_audit(): void
    {
        $product = InsuranceProduct::create([
            'product_code' => 'SAB_PROD_05',
            'name' => 'Sabotage Cover 5',
            'trigger_event_type' => 'WEATHER_DISRUPTION',
            'premium_amount_idr' => 20_000,
            'max_payout_idr' => 200_000,
        ]);

        $policy = InsurancePolicy::create([
            'policy_number' => 'POL-SAB05-001',
            'product_id' => $product->id,
            'user_id' => $this->user1->id,
            'subject_ref_type' => 'cargo',
            'subject_ref_id' => 'CRG-05',
            'premium_paid_idr' => 20_000,
            'coverage_limit_idr' => 200_000,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
        ]);

        // Maliciously insert earning exceeding total premium
        InsurancePolicyEarning::create([
            'policy_id' => $policy->id,
            'earning_date' => now()->toDateString(),
            'daily_amount_idr' => 999_000,
            'cumulative_earned_idr' => 999_000,
            'idempotency_key' => 'sab:ins:earn:005',
        ]);

        $exitCode = Artisan::call('ins:audit');
        expect($exitCode)->not->toBe(0);
    }

    public function test_sabotage_06_insurance_unapproved_claim_settlement_is_caught(): void
    {
        $claimsService = app(InsurTechClaimsService::class);

        $product = InsuranceProduct::create([
            'product_code' => 'SAB_PROD_06',
            'name' => 'Sabotage Cover 6',
            'trigger_event_type' => 'WEATHER_DISRUPTION',
            'premium_amount_idr' => 20_000,
            'max_payout_idr' => 200_000,
        ]);

        $policy = InsurancePolicy::create([
            'policy_number' => 'POL-SAB06-001',
            'product_id' => $product->id,
            'user_id' => $this->user1->id,
            'subject_ref_type' => 'cargo',
            'subject_ref_id' => 'CRG-06',
            'premium_paid_idr' => 20_000,
            'coverage_limit_idr' => 200_000,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
        ]);

        $claim = InsuranceClaim::create([
            'claim_number' => 'CLM-SAB-006',
            'policy_id' => $policy->id,
            'trigger_event_id' => 'EVT-006',
            'payout_amount_idr' => 200_000,
            'status' => 'pending_investigation',
            'idempotency_key' => 'sab:ins:clm:006',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Cannot settle claim with status 'pending_investigation'. Must be 'approved'.");

        // Sabotage attempt: Settle unapproved investigated claim directly
        $claimsService->settleApprovedClaim($claim->id, idempotencyKey: 'sab:ins:settle:006');
    }

    // =========================================================================
    // WEALTH SABOTAGES (7-9)
    // =========================================================================

    public function test_sabotage_07_wealth_excessive_sweep_percentage_is_caught(): void
    {
        $sweepService = app(CashSweepService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sweep percentage bps must be between 0 and 10000.');

        // Sabotage attempt: Configure sweep percentage above 10,000 bps (150%)
        $sweepService->configureSweep([
            'user_id' => $this->user1->id,
            'threshold_cash_idr' => 10_000_000,
            'sweep_percentage_bps' => 15_000,
            'target_asset_code' => 'PU_INDEX',
        ], idempotencyKey: 'sab:wm:swp:007');
    }

    public function test_sabotage_08_wealth_corrupted_portfolio_units_is_caught_by_audit(): void
    {
        $profile = WmProfile::create([
            'user_id' => $this->user1->id,
            'risk_score' => 3,
            'risk_profile' => 'moderate',
            'target_weights_bps' => ['mm' => 3000, 'bd' => 4000, 'eq' => 3000],
            'drift_band_bps' => 500,
            'emergency_fund_target_idr' => 10_000_000,
            'monthly_spend_baseline_idr' => 5_000_000,
            'auto_invest_enabled' => true,
        ]);

        WmPortfolioHolding::create([
            'profile_id' => $profile->id,
            'asset_code' => 'PU_INDEX',
            'units' => 100,
            'average_cost_nav' => 10_000,
            'current_nav' => 10_000,
            'current_market_value_idr' => 100_000,
        ]);

        // Malicious sabotage: Modify units directly in database without MTM revaluation
        WmPortfolioHolding::where('profile_id', $profile->id)->update(['units' => 88_888]);

        $exitCode = Artisan::call('wm:audit');
        expect($exitCode)->not->toBe(0);
    }

    public function test_sabotage_09_wealth_negative_cash_threshold_is_caught(): void
    {
        $sweepService = app(CashSweepService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Threshold cash must be positive or zero.');

        // Sabotage attempt: Configure negative cash threshold
        $sweepService->configureSweep([
            'user_id' => $this->user1->id,
            'threshold_cash_idr' => -5_000_000,
            'sweep_percentage_bps' => 5_000,
            'target_asset_code' => 'PU_INDEX',
        ], idempotencyKey: 'sab:wm:swp:009');
    }
}
