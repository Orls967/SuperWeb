<?php

declare(strict_types=1);

namespace Modules\Mall\tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Mall\Application\Actions\ExpireVouchersAction;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Enums\VoucherStatus;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Voucher;
use Modules\Mall\Ledger\MallAccounts;
use Tests\TestCase;

class ExpireVouchersActionTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Tenant $tenant;

    private ExpireVouchersAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BankingSeeder::class);
        $this->seed(MallSeeder::class);

        $this->customer = User::firstOrCreate(
            ['email' => 'customer.vch@duttamall.com'],
            ['name' => 'Voucher Customer', 'role' => 'customer', 'password' => bcrypt('password')]
        );

        $this->tenant = Tenant::firstOrFail();
        $this->action = app(ExpireVouchersAction::class);
    }

    public function test_returns_zero_when_no_active_vouchers_to_expire(): void
    {
        $expiredCount = $this->action->execute(Carbon::parse('2020-01-01'));
        $this->assertSame(0, $expiredCount);
    }

    public function test_expires_active_vouchers_and_posts_breakage_to_ledger(): void
    {
        $v1 = Voucher::create([
            'voucher_code' => 'VCH-EXP-001',
            'user_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'nominal_value' => 50000,
            'points_spent' => 500,
            'status' => VoucherStatus::ACTIVE,
            'expires_at' => Carbon::parse('2025-01-01'),
        ]);

        $v2 = Voucher::create([
            'voucher_code' => 'VCH-EXP-002',
            'user_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'nominal_value' => 75000,
            'points_spent' => 750,
            'status' => VoucherStatus::ACTIVE,
            'expires_at' => Carbon::parse('2025-01-02'),
        ]);

        $asOf = Carbon::parse('2025-01-05');
        $expiredCount = $this->action->execute($asOf);

        $this->assertSame(2, $expiredCount);

        $v1->refresh();
        $v2->refresh();
        $this->assertSame(VoucherStatus::EXPIRED, $v1->status);
        $this->assertSame(VoucherStatus::EXPIRED, $v2->status);

        // Check transaction key determinism: vch_breakage_{sorted_ids}
        $expectedIds = [$v1->id, $v2->id];
        sort($expectedIds);
        $expectedKey = 'vch_breakage_'.implode('_', $expectedIds);

        $tx = LedgerTransaction::where('idempotency_key', $expectedKey)->firstOrFail();
        $this->assertSame(1, $tx->created_by);
        $this->assertSame('Breakage 2 Voucher Kedaluwarsa (Total Rp 125,000)', $tx->description);

        $meta = is_array($tx->meta) ? $tx->meta : json_decode($tx->meta, true);
        $this->assertSame(2, $meta['count']);
        $this->assertSame(125000, $meta['total_nominal']);
        $this->assertSame($expectedIds, $meta['voucher_ids']);

        // Check entries
        $this->assertCount(2, $tx->entries);
        $liabilityAccount = MallAccounts::voucherLiabilityAccount();
        $breakageAccount = MallAccounts::voucherBreakageRevenueAccount();

        $debitEntry = $tx->entries->first(fn ($e) => bccomp($e->amount, '-125000') === 0);
        $creditEntry = $tx->entries->first(fn ($e) => bccomp($e->amount, '125000') === 0);
        $this->assertNotNull($debitEntry);
        $this->assertNotNull($creditEntry);
        $this->assertSame($liabilityAccount->id, $debitEntry->account_id);
        $this->assertSame($breakageAccount->id, $creditEntry->account_id);

        // Idempotency: re-running does not re-post or count again
        $reRun = $this->action->execute($asOf);
        $this->assertSame(0, $reRun);
        $this->assertSame(1, LedgerTransaction::where('idempotency_key', $expectedKey)->count());
    }
}
