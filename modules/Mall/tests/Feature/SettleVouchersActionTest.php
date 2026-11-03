<?php

declare(strict_types=1);

namespace Modules\Mall\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Mall\Application\Actions\SettleVouchersAction;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Enums\VoucherStatus;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Voucher;
use Modules\Mall\Ledger\MallAccounts;
use Tests\TestCase;

class SettleVouchersActionTest extends TestCase
{
    use RefreshDatabase;

    private User $tenantUser;

    private Tenant $tenant;

    private SettleVouchersAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BankingSeeder::class);
        $this->seed(MallSeeder::class);

        $this->tenantUser = User::firstOrCreate(
            ['email' => 'tenant.settle@duttamall.com'],
            ['name' => 'Settle Tenant User', 'role' => 'tenant', 'password' => bcrypt('password')]
        );

        $this->tenant = Tenant::firstOrFail();
        $this->tenant->update(['user_id' => $this->tenantUser->id]);
        $this->tenantUser->walletAccount('IDR');

        $this->action = app(SettleVouchersAction::class);
    }

    public function test_returns_empty_summary_when_no_used_vouchers(): void
    {
        $res = $this->action->execute();
        $this->assertSame(0, $res['settled_count']);
        $this->assertSame(0, $res['total_amount']);
        $this->assertSame(0, $res['tenants_count']);
    }

    public function test_settles_used_vouchers_for_tenant_and_replays_idempotently(): void
    {
        $v1 = Voucher::create([
            'voucher_code' => 'VCH-SET-001',
            'user_id' => $this->tenantUser->id,
            'tenant_id' => $this->tenant->id,
            'used_at_tenant_id' => $this->tenant->id,
            'nominal_value' => 50000,
            'points_spent' => 500,
            'status' => VoucherStatus::USED,
            'used_at' => now()->subDay(),
            'expires_at' => now()->addDays(10),
        ]);

        $v2 = Voucher::create([
            'voucher_code' => 'VCH-SET-002',
            'user_id' => $this->tenantUser->id,
            'tenant_id' => $this->tenant->id,
            'used_at_tenant_id' => $this->tenant->id,
            'nominal_value' => 100000,
            'points_spent' => 1000,
            'status' => VoucherStatus::USED,
            'used_at' => now()->subDay(),
            'expires_at' => now()->addDays(10),
        ]);

        $res = $this->action->execute($this->tenant->id);

        $this->assertSame(2, $res['settled_count']);
        $this->assertSame(150000, $res['total_amount']);
        $this->assertSame(1, $res['tenants_count']);

        $v1->refresh();
        $v2->refresh();
        $this->assertSame(VoucherStatus::SETTLED, $v1->status);
        $this->assertSame(VoucherStatus::SETTLED, $v2->status);
        $this->assertNotNull($v1->settled_at);
        $this->assertNotNull($v2->settled_at);
        $this->assertNotNull($v1->settlement_id);

        // Verify transaction
        $expectedKey = 'vch_settle_'.$this->tenant->id.'_'.$v1->id.'_'.$v2->id;
        $tx = LedgerTransaction::where('idempotency_key', $expectedKey)->firstOrFail();
        $this->assertSame(1, $tx->created_by);
        $this->assertSame('Settlement 2 Voucher Mall untuk Tenant RM Sari Ranah (Rp 150,000)', $tx->description);
        $meta = is_array($tx->meta) ? $tx->meta : json_decode($tx->meta, true);
        $this->assertSame($this->tenant->id, $meta['tenant_id']);
        $this->assertSame(2, $meta['vouchers_count']);
        $this->assertSame(150000, $meta['total_nominal']);
        $this->assertNotNull($meta['settlement_id']);

        // Check entries
        $this->assertCount(2, $tx->entries);
        $liabilityAccount = MallAccounts::voucherLiabilityAccount();
        $debitEntry = $tx->entries->first(fn ($e) => bccomp($e->amount, '-150000') === 0);
        $creditEntry = $tx->entries->first(fn ($e) => bccomp($e->amount, '150000') === 0);
        $this->assertNotNull($debitEntry);
        $this->assertNotNull($creditEntry);
        $this->assertSame($liabilityAccount->id, $debitEntry->account_id);
        $this->assertSame($this->tenantUser->walletAccount('IDR')->id, $creditEntry->account_id);

        // Replay idempotency test: running again returns summary and preserves settlement without double posting
        // Manually reset settled vouchers to test replay branch
        $v1->update(['status' => VoucherStatus::USED, 'settled_at' => null, 'settlement_id' => null]);
        $v2->update(['status' => VoucherStatus::USED, 'settled_at' => null, 'settlement_id' => null]);

        $replayRes = $this->action->execute($this->tenant->id);
        $this->assertSame(2, $replayRes['settled_count']);
        $this->assertSame(150000, $replayRes['total_amount']);
        $this->assertSame(1, LedgerTransaction::where('idempotency_key', $expectedKey)->count());
        $v1->refresh();
        $this->assertSame($meta['settlement_id'], $v1->settlement_id);
    }

    public function test_settles_all_tenants_when_no_tenant_id_provided(): void
    {
        $v = Voucher::create([
            'voucher_code' => 'VCH-SET-ALL-001',
            'user_id' => $this->tenantUser->id,
            'tenant_id' => $this->tenant->id,
            'used_at_tenant_id' => $this->tenant->id,
            'nominal_value' => 25000,
            'points_spent' => 250,
            'status' => VoucherStatus::USED,
            'used_at' => now()->subDay(),
            'expires_at' => now()->addDays(10),
        ]);

        $res = $this->action->execute(null);
        $this->assertSame(1, $res['settled_count']);
        $this->assertSame(25000, $res['total_amount']);
        $this->assertSame(1, $res['tenants_count']);
    }
}
