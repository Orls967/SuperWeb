<?php

declare(strict_types=1);

namespace Modules\Wealth\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Wealth\Application\Services\RoboAdvisorService;
use Modules\Wealth\Domain\Models\WmHolding;
use Tests\TestCase;

class WealthTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        LedgerAccount::create([
            'code' => "wallet:user:{$this->user->id}:IDR",
            'name' => "User {$this->user->id} Wallet",
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '25000000',
        ]);

        LedgerAccount::create([
            'code' => 'treasury:pool:IDR',
            'name' => 'Treasury Pool',
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);
    }

    public function test_liquidity_guardrail_prevents_investing_emergency_fund(): void
    {
        /** @var RoboAdvisorService $service */
        $service = app(RoboAdvisorService::class);

        // Monthly spend 5,000,000 IDR => Emergency target 10,000,000 IDR
        $profile = $service->configureProfile(
            userId: $this->user->id,
            riskProfile: 'moderate',
            monthlySpendBaselineIdr: 5000000
        );

        $this->assertEquals(10000000, $profile->emergency_fund_target_idr);

        // Wallet balance exactly 9,000,000 IDR (below emergency fund) => 0 invested
        $invested = $service->executeSurplusAutoInvest($profile, 9000000, '2026-10');
        $this->assertEquals(0, $invested);
        $this->assertEquals(0, WmHolding::where('profile_id', $profile->id)->sum('value_idr'));
    }

    public function test_surplus_auto_invest_allocates_correctly_and_is_idempotent(): void
    {
        /** @var RoboAdvisorService $service */
        $service = app(RoboAdvisorService::class);

        $profile = $service->configureProfile(
            userId: $this->user->id,
            riskProfile: 'moderate',
            monthlySpendBaselineIdr: 5000000,
            allocationPcts: [50, 30, 20]
        );

        // Wallet balance 20,000,000 IDR => Surplus = 20M - 10M = 10,000,000 IDR
        $invested1 = $service->executeSurplusAutoInvest($profile, 20000000, '2026-10');
        $this->assertEquals(10000000, $invested1);

        $mfHolding = WmHolding::where('profile_id', $profile->id)->where('asset_type', 'mutual_fund')->first();
        $goldHolding = WmHolding::where('profile_id', $profile->id)->where('asset_type', 'digital_gold')->first();
        $cryptoHolding = WmHolding::where('profile_id', $profile->id)->where('asset_type', 'crypto')->first();

        $this->assertEquals(5000000, $mfHolding->value_idr);
        $this->assertEquals(3000000, $goldHolding->value_idr);
        $this->assertEquals(2000000, $cryptoHolding->value_idr);
        $this->assertEquals(10000000, $mfHolding->value_idr + $goldHolding->value_idr + $cryptoHolding->value_idr);

        // Idempotency: re-running for same cycle should not re-invest
        $invested2 = $service->executeSurplusAutoInvest($profile, 20000000, '2026-10');
        $this->assertEquals($invested1, $invested2);
    }
}
