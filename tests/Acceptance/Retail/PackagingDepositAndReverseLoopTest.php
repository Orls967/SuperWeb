<?php

declare(strict_types=1);

namespace Tests\Acceptance\Retail;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Ret\Application\Actions\ForfeitUnreturnedPackagingDepositAction;
use Modules\Ret\Application\Actions\IssuePackagingDepositAction;
use Modules\Ret\Application\Actions\RefundPackagingDepositAction;
use Modules\Ret\Domain\Exceptions\PackagingAlreadyReturnedException;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Ret\Domain\Models\RetPackagingDeposit;
use Tests\TestCase;

class PackagingDepositAndReverseLoopTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected RetOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->customer = User::factory()->create(['role' => 'customer']);

        $channel = RetChannel::create([
            'id' => (string) Str::uuid(),
            'channel_code' => 'PKG_STORE',
            'channel_name' => 'Packaging Store',
            'channel_type' => 'STORE',
            'default_commission_bps' => 500,
            'status' => 'ACTIVE',
        ]);

        $this->order = RetOrder::create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-20261031-PKG01',
            'customer_id' => $this->customer->id,
            'channel_id' => $channel->id,
            'total_amount_minor' => 120_000,
            'packaging_deposit_minor' => 20_000,
            'status' => 'PAID',
            'idempotency_key' => 'ret:order:pkg-setup-01',
        ]);
    }

    public function test_order_with_reusable_container_charges_deposit_to_liability(): void
    {
        $action = app(IssuePackagingDepositAction::class);

        $deposit = $action->execute([
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'package_code' => 'TOTE-REUSE-101',
            'deposit_amount_minor' => 20_000,
            'idempotency_key' => 'ret:pkg:issue-01',
        ]);

        $this->assertInstanceOf(RetPackagingDeposit::class, $deposit);
        $this->assertEquals('HELD', $deposit->status);
        $this->assertEquals(20_000, $deposit->deposit_amount_minor);

        // Check liability ledger (credit +)
        $liabilityEntry = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:deposit_liability:IDR'))
            ->latest('id')
            ->first();
        $this->assertNotNull($liabilityEntry);
        $this->assertEquals(20_000, (int) $liabilityEntry->amount);
    }

    public function test_returning_package_in_good_condition_refunds_deposit(): void
    {
        $issueAction = app(IssuePackagingDepositAction::class);
        $deposit = $issueAction->execute([
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'package_code' => 'TOTE-REUSE-102',
            'deposit_amount_minor' => 25_000,
            'idempotency_key' => 'ret:pkg:issue-02',
        ]);

        $refundAction = app(RefundPackagingDepositAction::class);
        $refundedDeposit = $refundAction->execute([
            'deposit_id' => $deposit->id,
            'inspected_condition' => 'GOOD',
            'return_station_id' => 'STATION-JAKSEL-01',
            'idempotency_key' => 'ret:pkg:refund-01',
        ]);

        $this->assertEquals('REFUNDED', $refundedDeposit->status);

        // Liability debited (reversed -25_000)
        $liabilityDebit = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:deposit_liability:IDR'))
            ->where('amount', '<', 0)
            ->latest('id')
            ->first();
        $this->assertNotNull($liabilityDebit);
        $this->assertEquals(-25_000, (int) $liabilityDebit->amount);
    }

    public function test_double_return_attempt_rejected(): void
    {
        $issueAction = app(IssuePackagingDepositAction::class);
        $deposit = $issueAction->execute([
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'package_code' => 'TOTE-REUSE-103',
            'deposit_amount_minor' => 20_000,
            'idempotency_key' => 'ret:pkg:issue-03',
        ]);

        $refundAction = app(RefundPackagingDepositAction::class);
        $refundAction->execute([
            'deposit_id' => $deposit->id,
            'inspected_condition' => 'GOOD',
            'return_station_id' => 'STATION-01',
            'idempotency_key' => 'ret:pkg:refund-02',
        ]);

        $this->expectException(PackagingAlreadyReturnedException::class);
        $refundAction->execute([
            'deposit_id' => $deposit->id,
            'inspected_condition' => 'GOOD',
            'return_station_id' => 'STATION-01',
            'idempotency_key' => 'ret:pkg:refund-double',
        ]);
    }

    public function test_forfeited_deposit_recognizes_revenue_and_clears_liability(): void
    {
        $issueAction = app(IssuePackagingDepositAction::class);
        $deposit = $issueAction->execute([
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'package_code' => 'TOTE-LOST-01',
            'deposit_amount_minor' => 30_000,
            'idempotency_key' => 'ret:pkg:issue-04',
        ]);

        $forfeitAction = app(ForfeitUnreturnedPackagingDepositAction::class);
        $forfeited = $forfeitAction->execute([
            'deposit_id' => $deposit->id,
            'reason' => 'Retention window expired (90 days) without return',
            'idempotency_key' => 'ret:pkg:forfeit-01',
        ]);

        $this->assertEquals('FORFEITED', $forfeited->status);

        // Liability debited (-30_000)
        $liabilityDeb = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:deposit_liability:IDR'))
            ->where('amount', '<', 0)
            ->latest('id')
            ->first();
        $this->assertNotNull($liabilityDeb);
        $this->assertEquals(-30_000, (int) $liabilityDeb->amount);

        // Revenue credited (+30_000)
        $revCred = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:omnichannel_sales_revenue:IDR'))
            ->latest('id')
            ->first();
        $this->assertNotNull($revCred);
        $this->assertEquals(30_000, (int) $revCred->amount);
    }

    public function test_total_held_deposits_equals_ledger_deposit_liability(): void
    {
        $issueAction = app(IssuePackagingDepositAction::class);
        $refundAction = app(RefundPackagingDepositAction::class);

        $d1 = $issueAction->execute([
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'package_code' => 'TOTE-SUM-1',
            'deposit_amount_minor' => 15_000,
            'idempotency_key' => 'ret:pkg:sum-01',
        ]);

        $d2 = $issueAction->execute([
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'package_code' => 'TOTE-SUM-2',
            'deposit_amount_minor' => 20_000,
            'idempotency_key' => 'ret:pkg:sum-02',
        ]);

        $refundAction->execute([
            'deposit_id' => $d1->id,
            'inspected_condition' => 'GOOD',
            'return_station_id' => 'STN-1',
            'idempotency_key' => 'ret:pkg:sum-ref-01',
        ]);

        // Net liability should be 20_000 (from d2)
        $liabilityNet = (int) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:deposit_liability:IDR'))->sum('amount');
        $this->assertEquals(20_000, $liabilityNet);
    }

    public function test_deposit_issue_requires_idempotency_key(): void
    {
        $action = app(IssuePackagingDepositAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $action->execute([
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'package_code' => 'TOTE-NOKEY',
            'deposit_amount_minor' => 10_000,
            // missing key
        ]);
    }

    public function test_duplicate_deposit_refund_with_same_key_is_idempotent(): void
    {
        $issueAction = app(IssuePackagingDepositAction::class);
        $deposit = $issueAction->execute([
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'package_code' => 'TOTE-IDEM-REF',
            'deposit_amount_minor' => 15_000,
            'idempotency_key' => 'ret:pkg:issue-idem-ref',
        ]);

        $refundAction = app(RefundPackagingDepositAction::class);
        $key = 'ret:pkg:refund-idem-key';

        $r1 = $refundAction->execute([
            'deposit_id' => $deposit->id,
            'inspected_condition' => 'GOOD',
            'return_station_id' => 'STN-IDEM',
            'idempotency_key' => $key,
        ]);

        $r2 = $refundAction->execute([
            'deposit_id' => $deposit->id,
            'inspected_condition' => 'GOOD',
            'return_station_id' => 'STN-IDEM',
            'idempotency_key' => $key,
        ]);

        $this->assertEquals($r1->id, $r2->id);
    }
}
