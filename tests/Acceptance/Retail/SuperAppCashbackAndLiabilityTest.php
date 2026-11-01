<?php

declare(strict_types=1);

namespace Tests\Acceptance\Retail;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Ret\Application\Actions\EarnSuperAppCashbackAction;
use Modules\Ret\Application\Actions\RedeemSuperAppCashbackAction;
use Modules\Ret\Domain\Exceptions\CashbackVelocityExceededException;
use Modules\Ret\Domain\Exceptions\InsufficientCashbackBalanceException;
use Modules\Ret\Domain\Models\RetCashbackTransaction;
use Tests\TestCase;

class SuperAppCashbackAndLiabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->customer = User::factory()->create(['role' => 'customer']);
    }

    public function test_earning_cashback_creates_liability_on_ledger_and_marketing_expense(): void
    {
        $action = app(EarnSuperAppCashbackAction::class);

        $tx = $action->execute([
            'customer_id' => $this->customer->id,
            'amount_minor' => 25_000,
            'source_domain' => 'resto',
            'reference_id' => 'resto-order-99',
            'idempotency_key' => 'ret:cashback:earn-01',
        ]);

        $this->assertInstanceOf(RetCashbackTransaction::class, $tx);
        $this->assertEquals(25_000, $tx->amount_minor);
        $this->assertEquals('EARNED', $tx->type);

        // Check liability ledger (credit +)
        $liabilityEntry = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:cashback_liability:IDR'))
            ->latest('id')
            ->first();
        $this->assertNotNull($liabilityEntry);
        $this->assertEquals(25_000, (int) $liabilityEntry->amount);

        // Check marketing expense ledger (debit -)
        $expenseEntry = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:cashback_promo_expense:IDR'))
            ->latest('id')
            ->first();
        $this->assertNotNull($expenseEntry);
        $this->assertEquals(-25_000, (int) $expenseEntry->amount);
    }

    public function test_redeeming_cashback_reduces_liability_and_covers_payment(): void
    {
        $earnAction = app(EarnSuperAppCashbackAction::class);
        $earnAction->execute([
            'customer_id' => $this->customer->id,
            'amount_minor' => 50_000,
            'source_domain' => 'hotel',
            'reference_id' => 'htl-booking-01',
            'idempotency_key' => 'ret:cashback:earn-02',
        ]);

        $redeemAction = app(RedeemSuperAppCashbackAction::class);
        $redemption = $redeemAction->execute([
            'customer_id' => $this->customer->id,
            'amount_minor' => 30_000,
            'order_id' => 'ord-cart-checkout-01',
            'idempotency_key' => 'ret:cashback:redeem-01',
        ]);

        $this->assertEquals(30_000, $redemption->amount_minor);
        $this->assertEquals('REDEEMED', $redemption->type);

        // Check liability debit (reversal/usage -30_000)
        $liabilityDebit = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:cashback_liability:IDR'))
            ->where('amount', '<', 0)
            ->latest('id')
            ->first();
        $this->assertNotNull($liabilityDebit);
        $this->assertEquals(-30_000, (int) $liabilityDebit->amount);
    }

    public function test_redeeming_more_cashback_than_available_is_rejected(): void
    {
        $earnAction = app(EarnSuperAppCashbackAction::class);
        $earnAction->execute([
            'customer_id' => $this->customer->id,
            'amount_minor' => 10_000,
            'source_domain' => 'venue',
            'reference_id' => 'ven-ticket-01',
            'idempotency_key' => 'ret:cashback:earn-03',
        ]);

        $redeemAction = app(RedeemSuperAppCashbackAction::class);

        $this->expectException(InsufficientCashbackBalanceException::class);
        $redeemAction->execute([
            'customer_id' => $this->customer->id,
            'amount_minor' => 15_000, // exceeds 10,000
            'order_id' => 'ord-fail-01',
            'idempotency_key' => 'ret:cashback:redeem-fail',
        ]);
    }

    public function test_anti_abuse_velocity_rule_blocks_excessive_earnings_per_day(): void
    {
        $action = app(EarnSuperAppCashbackAction::class);

        // Daily limit is 500_000 IDR cashback per customer
        $action->execute([
            'customer_id' => $this->customer->id,
            'amount_minor' => 450_000,
            'source_domain' => 'store',
            'reference_id' => 'store-big-01',
            'idempotency_key' => 'ret:cashback:earn-vel-1',
        ]);

        $this->expectException(CashbackVelocityExceededException::class);
        $action->execute([
            'customer_id' => $this->customer->id,
            'amount_minor' => 100_000, // Total would be 550_000 > 500_000 limit
            'source_domain' => 'store',
            'reference_id' => 'store-big-02',
            'idempotency_key' => 'ret:cashback:earn-vel-2',
        ]);
    }

    public function test_cashback_expiry_breakage_recognizes_breakage_revenue(): void
    {
        $earnAction = app(EarnSuperAppCashbackAction::class);
        $tx = $earnAction->execute([
            'customer_id' => $this->customer->id,
            'amount_minor' => 40_000,
            'source_domain' => 'resto',
            'reference_id' => 'resto-exp-01',
            'idempotency_key' => 'ret:cashback:earn-exp-1',
            'expires_at' => now()->subDay(), // already expired
        ]);

        Artisan::call('ret:expire-cashback');

        // Liability debited (eliminated)
        $liabilityElim = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:cashback_liability:IDR'))
            ->where('amount', '<', 0)
            ->latest('id')
            ->first();
        $this->assertNotNull($liabilityElim);
        $this->assertEquals(-40_000, (int) $liabilityElim->amount);

        // Breakage revenue credited (+)
        $breakageRev = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:cashback_breakage_revenue:IDR'))
            ->latest('id')
            ->first();
        $this->assertNotNull($breakageRev);
        $this->assertEquals(40_000, (int) $breakageRev->amount);
    }

    public function test_artisan_command_expire_cashback_is_idempotent(): void
    {
        $earnAction = app(EarnSuperAppCashbackAction::class);
        $earnAction->execute([
            'customer_id' => $this->customer->id,
            'amount_minor' => 20_000,
            'source_domain' => 'resto',
            'reference_id' => 'resto-exp-02',
            'idempotency_key' => 'ret:cashback:earn-exp-2',
            'expires_at' => now()->subDay(),
        ]);

        $exitCode1 = Artisan::call('ret:expire-cashback');
        $exitCode2 = Artisan::call('ret:expire-cashback');

        $this->assertEquals(0, $exitCode1);
        $this->assertEquals(0, $exitCode2);

        $expiredCount = RetCashbackTransaction::where('type', 'EXPIRED')->count();
        $this->assertEquals(1, $expiredCount);
    }

    public function test_total_cashback_liability_on_ledger_equals_active_unexpired_tranches(): void
    {
        $earnAction = app(EarnSuperAppCashbackAction::class);
        $redeemAction = app(RedeemSuperAppCashbackAction::class);

        $earnAction->execute([
            'customer_id' => $this->customer->id,
            'amount_minor' => 100_000,
            'source_domain' => 'store',
            'reference_id' => 'ref-sum-1',
            'idempotency_key' => 'ret:cashback:sum-1',
        ]);

        $redeemAction->execute([
            'customer_id' => $this->customer->id,
            'amount_minor' => 30_000,
            'order_id' => 'ref-sum-ord-1',
            'idempotency_key' => 'ret:cashback:sum-redeem-1',
        ]);

        $ledgerSum = (int) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:cashback_liability:IDR'))->sum('amount');
        $this->assertEquals(70_000, $ledgerSum);
    }

    public function test_cashback_transaction_is_append_only_and_rejects_update(): void
    {
        $earnAction = app(EarnSuperAppCashbackAction::class);
        $tx = $earnAction->execute([
            'customer_id' => $this->customer->id,
            'amount_minor' => 15_000,
            'source_domain' => 'store',
            'reference_id' => 'ref-app-1',
            'idempotency_key' => 'ret:cashback:app-1',
        ]);

        $this->expectException(\DomainException::class);
        $tx->update(['amount_minor' => 999_999]);
    }

    public function test_cashback_transaction_is_append_only_and_rejects_delete(): void
    {
        $earnAction = app(EarnSuperAppCashbackAction::class);
        $tx = $earnAction->execute([
            'customer_id' => $this->customer->id,
            'amount_minor' => 15_000,
            'source_domain' => 'store',
            'reference_id' => 'ref-del-1',
            'idempotency_key' => 'ret:cashback:del-1',
        ]);

        $this->expectException(\DomainException::class);
        $tx->delete();
    }

    public function test_earning_cashback_requires_idempotency_key(): void
    {
        $action = app(EarnSuperAppCashbackAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $action->execute([
            'customer_id' => $this->customer->id,
            'amount_minor' => 10_000,
            'source_domain' => 'store',
            'reference_id' => 'ref-nokey-1',
            // missing key
        ]);
    }
}
