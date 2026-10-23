<?php

declare(strict_types=1);

namespace Modules\Treasury\tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Treasury\Application\Actions\ExecuteNotionalCashPoolSweepAction;
use Modules\Treasury\Application\Actions\RunMultiCurrencyFxRevaluationAction;
use Modules\Treasury\Application\Actions\SettleForwardFxHedgeContractAction;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\CashPool;
use Modules\Treasury\Domain\Models\Currency;
use Modules\Treasury\Domain\Models\ForwardContract;
use Tests\TestCase;

class TreasuryOperationsSliceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BankAccount $headerAccount;

    private BankAccount $subAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::first() ?? User::factory()->create(['role' => 'admin']);

        Currency::firstOrCreate(
            ['code' => 'IDR'],
            ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'minor_units' => 0]
        );
        Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'minor_units' => 2]
        );

        $this->headerAccount = BankAccount::create([
            'account_number' => 'ACC-HDR-001',
            'bank_name' => 'Bank Mandiri Corporate',
            'currency' => 'IDR',
            'balance' => 5000000000,
            'is_petty_cash' => false,
            'is_active' => true,
        ]);

        $this->subAccount = BankAccount::create([
            'account_number' => 'ACC-SUB-001',
            'bank_name' => 'Bank Mandiri Branch Bandung',
            'currency' => 'IDR',
            'balance' => 200000000,
            'is_petty_cash' => false,
            'is_active' => true,
        ]);
    }

    public function test_run_multi_currency_fx_revaluation_calculates_gain_correctly(): void
    {
        $action = app(RunMultiCurrencyFxRevaluationAction::class);

        // Foreign balance: 10,000 USD (1,000,000 cents). Book rate: 15,000 IDR -> 150,000,000 IDR.
        // Current spot rate: 16,000 IDR (160,000,000 scaled).
        // Revalued: (1,000,000 * 160,000,000) / 1,000,000 = 160,000,000 IDR.
        // Gain: 160,000,000 - 150,000,000 = 10,000,000 IDR.
        $res = $action->execute(
            period: '2026-10',
            currency: 'USD',
            foreignBalanceMinor: 1000000,
            bookFunctionalIdr: 150000000,
            spotRateScaled: 160000000
        );

        $this->assertSame(160000000, (int) $res['revaluation']->revalued_functional_idr);
        $this->assertSame(10000000, $res['gain_loss_idr']);
        $this->assertSame('posted', $res['status']);
    }

    public function test_run_multi_currency_fx_revaluation_calculates_loss_correctly(): void
    {
        $action = app(RunMultiCurrencyFxRevaluationAction::class);

        // Book 160M, spot drops to 14,500 IDR (145M). Loss = -15M.
        $res = $action->execute(
            period: '2026-10',
            currency: 'USD',
            foreignBalanceMinor: 1000000,
            bookFunctionalIdr: 160000000,
            spotRateScaled: 145000000
        );

        $this->assertSame(145000000, (int) $res['revaluation']->revalued_functional_idr);
        $this->assertSame(-15000000, $res['gain_loss_idr']);
    }

    public function test_execute_notional_cash_pool_sweep_upstream_direction(): void
    {
        $pool = CashPool::create([
            'pool_name' => 'Main Operating Cash Pool',
            'header_account_id' => (string) $this->headerAccount->id,
            'sub_account_id' => (string) $this->subAccount->id,
            'target_balance_idr' => 50000000, // Target 50M
        ]);

        $action = app(ExecuteNotionalCashPoolSweepAction::class);

        // Current sub account balance: 80M. Excess: 30M swept to header!
        $res = $action->execute($pool, currentSubAccountBalanceIdr: 80000000);

        $this->assertSame(30000000, $res['swept_amount_idr']);
        $this->assertSame('upstream_sweep_to_header', $res['sweep_direction']);
    }

    public function test_execute_notional_cash_pool_sweep_downstream_direction(): void
    {
        $pool = CashPool::create([
            'pool_name' => 'Payroll Cash Pool',
            'header_account_id' => (string) $this->headerAccount->id,
            'sub_account_id' => (string) $this->subAccount->id,
            'target_balance_idr' => 100000000, // Target 100M
        ]);

        $action = app(ExecuteNotionalCashPoolSweepAction::class);

        // Current balance: 40M. Deficit: 60M topped up from header!
        $res = $action->execute($pool, currentSubAccountBalanceIdr: 40000000);

        $this->assertSame(60000000, $res['swept_amount_idr']);
        $this->assertSame('downstream_topup_from_header', $res['sweep_direction']);
    }

    public function test_settle_forward_fx_hedge_contract_settles_gain_at_maturity(): void
    {
        $contract = ForwardContract::create([
            'contract_number' => 'FWD-USD-2026-001',
            'currency' => 'USD',
            'notional_foreign_amount' => 5000000, // 50,000 USD in cents
            'forward_rate_scaled' => 155000000,   // Locked rate 15,500 IDR
            'maturity_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $action = app(SettleForwardFxHedgeContractAction::class);

        // At maturity, market spot rose to 16,000 IDR (160,000,000 scaled)
        // Forward locked value: 50,000 * 15,500 = 775,000,000 IDR
        // Spot value: 50,000 * 16,000 = 800,000,000 IDR
        // Hedging gain: 25,000,000 IDR
        $res = $action->execute($contract, spotRateAtMaturityScaled: 160000000);

        $this->assertSame('settled', $res['status']);
        $this->assertSame(25000000, $res['net_settlement_idr']);
        $this->assertSame(25000000, (int) $contract->fresh()->mtm_value_idr);
    }

    public function test_treasury_operations_controller_endpoints(): void
    {
        $this->actingAs($this->user);

        $pool = CashPool::create([
            'pool_name' => 'API Test Pool',
            'header_account_id' => (string) $this->headerAccount->id,
            'sub_account_id' => (string) $this->subAccount->id,
            'target_balance_idr' => 50000000,
        ]);

        $respSweep = $this->postJson("/treasury/cash-pools/{$pool->id}/sweep", [
            'current_sub_account_balance_idr' => 70000000,
        ]);
        $respSweep->assertOk();
        $respSweep->assertJsonFragment(['swept_amount_idr' => 20000000]);

        $contract = ForwardContract::create([
            'contract_number' => 'FWD-API-001',
            'currency' => 'USD',
            'notional_foreign_amount' => 1000000,
            'forward_rate_scaled' => 150000000,
            'maturity_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $respSettle = $this->postJson("/treasury/forward-contracts/{$contract->id}/settle", [
            'spot_rate_at_maturity_scaled' => 155000000,
        ]);
        $respSettle->assertOk();
        $respSettle->assertJsonFragment(['net_settlement_idr' => 5000000]);
    }
}
