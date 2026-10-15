<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Core\database\seeders\RbacSeeder;
use Modules\Crypto\Domain\Enums\AlertCondition;
use Modules\Crypto\Domain\Models\CryptoAlert;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Finance\Domain\Models\Loan;
use Tests\TestCase;

class FinanceWalletCryptoAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(BankingSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('wallet.index'))->assertRedirect('/login');
        $this->get(route('finance.loans.index'))->assertRedirect('/login');
        $this->get(route('crypto.portfolio.index'))->assertRedirect('/login');
        $this->get(route('crypto.alerts.index'))->assertRedirect('/login');
    }

    public function test_auditor_is_strictly_forbidden_from_financial_mutations(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);

        // Wallet mutations: auditor forbidden
        $this->actingAs($auditor)->post(route('wallet.pin.store'), ['pin' => '123456', 'pin_confirmation' => '123456'])->assertForbidden();
        $this->actingAs($auditor)->post(route('wallet.topup.store'), ['amount' => 500000])->assertForbidden();
        $this->actingAs($auditor)->post(route('wallet.transfer.store'), ['recipient' => 'test@example.com', 'amount' => 50000, 'pin' => '123456'])->assertForbidden();

        // Finance loans mutations: auditor forbidden
        $this->actingAs($auditor)->post(route('finance.loans.quote'), ['principal' => 10000000, 'tenor_months' => 12, 'symbol' => 'BTC'])->assertForbidden();

        // Crypto trading mutations: auditor forbidden
        $this->actingAs($auditor)->post(route('crypto.trade.quote'), ['symbol' => 'BTC', 'side' => 'buy', 'amount' => 100000])->assertForbidden();
        $this->actingAs($auditor)->post(route('crypto.trade.execute'), ['quote_id' => 'dummy'])->assertForbidden();
        $this->actingAs($auditor)->post(route('crypto.alerts.store'), ['asset_id' => 1, 'condition' => 'above', 'target_price_idr' => 1000000000])->assertForbidden();
    }

    public function test_unauthorized_roles_receive_403_on_finance_and_wallet(): void
    {
        $unauthorized = User::factory()->create(['role' => 'operator']);

        $this->actingAs($unauthorized)->get(route('wallet.index'))->assertForbidden();
        $this->actingAs($unauthorized)->get(route('wallet.mutasi'))->assertForbidden();
        $this->actingAs($unauthorized)->get(route('finance.loans.index'))->assertForbidden();
        $this->actingAs($unauthorized)->get(route('crypto.portfolio.index'))->assertForbidden();
        $this->actingAs($unauthorized)->get(route('crypto.alerts.index'))->assertForbidden();
    }

    public function test_authorized_roles_can_read_data(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $treasury = User::factory()->create(['role' => 'treasury']);
        $auditor = User::factory()->create(['role' => 'auditor']);

        // Customer read access
        $this->actingAs($customer)->get(route('wallet.index'))->assertSuccessful();
        $this->actingAs($customer)->get(route('finance.loans.index'))->assertSuccessful();
        $this->actingAs($customer)->get(route('crypto.portfolio.index'))->assertSuccessful();
        $this->actingAs($customer)->get(route('crypto.alerts.index'))->assertSuccessful();

        // Auditor read access
        $this->actingAs($auditor)->get(route('wallet.index'))->assertSuccessful();
        $this->actingAs($auditor)->get(route('finance.loans.index'))->assertSuccessful();
        $this->actingAs($auditor)->get(route('crypto.portfolio.index'))->assertSuccessful();
        $this->actingAs($auditor)->get(route('crypto.alerts.index'))->assertSuccessful();

        // Treasury read access on loans & crypto
        $this->actingAs($treasury)->get(route('finance.loans.index'))->assertSuccessful();
        $this->actingAs($treasury)->get(route('crypto.portfolio.index'))->assertSuccessful();
    }

    public function test_ownership_isolation_between_customers(): void
    {
        $customerA = User::factory()->create(['role' => 'customer']);
        $customerB = User::factory()->create(['role' => 'customer']);

        $asset = CryptoAsset::firstOrCreate(
            ['symbol' => 'BTC'],
            ['name' => 'Bitcoin', 'decimals' => 8, 'volatility' => 0.03, 'is_active' => true]
        );

        $loanA = Loan::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $customerA->id,
            'status' => 'active',
            'principal' => 100000000,
            'outstanding_principal' => 100000000,
            'interest_rate_annual' => 0.08,
            'tenor_months' => 12,
            'collateral_asset_id' => $asset->id,
            'collateral_qty' => '0.2',
            'ltv_at_open' => 0.5,
            'opened_at' => now(),
        ]);

        $alertA = CryptoAlert::create([
            'user_id' => $customerA->id,
            'asset_id' => $asset->id,
            'condition' => AlertCondition::ABOVE,
            'target_price_idr' => '1100000000',
            'is_triggered' => false,
        ]);

        // Customer A can view own loan
        $this->actingAs($customerA)->get(route('finance.loans.show', $loanA))->assertSuccessful();

        // Customer B is forbidden from viewing or paying Customer A's loan
        $this->actingAs($customerB)->get(route('finance.loans.show', $loanA))->assertForbidden();
        $this->actingAs($customerB)->post(route('finance.loans.payInstallment', $loanA))->assertForbidden();

        // Customer B is forbidden from destroying Customer A's price alert
        $this->actingAs($customerB)->delete(route('crypto.alerts.destroy', $alertA))->assertForbidden();
    }
}
