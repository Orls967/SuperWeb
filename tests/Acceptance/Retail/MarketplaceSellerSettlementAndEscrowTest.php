<?php

declare(strict_types=1);

namespace Tests\Acceptance\Retail;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Ret\Application\Actions\CheckoutOmnichannelOrderAction;
use Modules\Ret\Application\Actions\ProcessOrderRefundAction;
use Modules\Ret\Application\Actions\SettleRetailSellerEscrowAction;
use Modules\Ret\Domain\Exceptions\IdempotencyConflictException;
use Modules\Ret\Domain\Exceptions\SellerNotEligibleException;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Ret\Domain\Models\RetSeller;
use Modules\Ret\Domain\Models\RetSellerSettlement;
use Tests\TestCase;

/**
 * @covers \Modules\Ret\Application\Actions\SettleRetailSellerEscrowAction
 */
class MarketplaceSellerSettlementAndEscrowTest extends TestCase
{
    use RefreshDatabase;

    protected RetChannel $channel;

    protected RetSeller $seller;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->channel = RetChannel::create([
            'id' => (string) Str::uuid(),
            'channel_code' => 'MARKETPLACE_3P',
            'channel_name' => 'Superweb 3P Marketplace',
            'channel_type' => 'MARKETPLACE_3P',
            'default_commission_bps' => 1000, // 10%
            'status' => 'ACTIVE',
        ]);

        $this->seller = RetSeller::create([
            'id' => (string) Str::uuid(),
            'party_id' => 'party-elec-99',
            'seller_name' => 'Mega Electronics Official',
            'kyb_status' => 'VERIFIED',
            'commission_override_bps' => 1000, // 10%
            'payout_account_id' => 'bank-acc-99',
        ]);

        $this->customer = User::factory()->create(['role' => 'customer']);
    }

    protected function createPaidOrder(int $amountMinor, string $idempotencyKey): RetOrder
    {
        $action = app(CheckoutOmnichannelOrderAction::class);

        return $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-ELEC-01', 'quantity' => 1, 'unit_price_minor' => $amountMinor],
            ],
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function test_seller_settlement_releases_escrow_to_seller_ap_and_marketplace_commission(): void
    {
        $order = $this->createPaidOrder(1_000_000, 'ret:checkout:settle-test-01');
        $order->update(['status' => 'DELIVERED']);

        $settleAction = app(SettleRetailSellerEscrowAction::class);
        $settlement = $settleAction->execute([
            'order_id' => $order->id,
            'idempotency_key' => 'ret:settle:ord-01',
        ]);

        $this->assertInstanceOf(RetSellerSettlement::class, $settlement);
        $this->assertEquals(100_000, $settlement->commission_amount_minor); // 10% of 1,000,000
        $this->assertEquals(900_000, $settlement->net_seller_payable_minor); // 900,000 to seller

        // Check Ledger Postings
        $commissionEntry = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:commission_revenue:IDR'))
            ->latest('id')
            ->first();
        $this->assertNotNull($commissionEntry);
        $this->assertEquals(100_000, (int) $commissionEntry->amount);

        $sellerApEntry = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', "ap:ret:seller:{$this->seller->party_id}:IDR"))
            ->latest('id')
            ->first();
        $this->assertNotNull($sellerApEntry);
        $this->assertEquals(900_000, (int) $sellerApEntry->amount);
    }

    public function test_commission_split_preserves_exact_sum_via_hamilton_rounding(): void
    {
        // 333,333 IDR at 10% commission = 33,333.3 IDR -> HalfUp rounds to 33,333 IDR commission, 300,000 seller
        $order = $this->createPaidOrder(333_333, 'ret:checkout:hamilton-01');
        $order->update(['status' => 'DELIVERED']);

        $settleAction = app(SettleRetailSellerEscrowAction::class);
        $settlement = $settleAction->execute([
            'order_id' => $order->id,
            'idempotency_key' => 'ret:settle:hamilton-01',
        ]);

        $sum = $settlement->commission_amount_minor + $settlement->net_seller_payable_minor;
        $this->assertEquals(333_333, $sum);
    }

    public function test_settlement_requires_idempotency_key(): void
    {
        $order = $this->createPaidOrder(500_000, 'ret:checkout:no-key-01');
        $order->update(['status' => 'DELIVERED']);

        $settleAction = app(SettleRetailSellerEscrowAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $settleAction->execute([
            'order_id' => $order->id,
            // missing idempotency_key
        ]);
    }

    public function test_duplicate_settlement_with_same_key_returns_existing_record(): void
    {
        $order = $this->createPaidOrder(800_000, 'ret:checkout:dup-settle-01');
        $order->update(['status' => 'DELIVERED']);

        $settleAction = app(SettleRetailSellerEscrowAction::class);
        $key = 'ret:settle:dup-test-01';

        $s1 = $settleAction->execute(['order_id' => $order->id, 'idempotency_key' => $key]);
        $s2 = $settleAction->execute(['order_id' => $order->id, 'idempotency_key' => $key]);

        $this->assertEquals($s1->id, $s2->id);
    }

    public function test_conflicting_settlement_payload_with_same_key_throws_conflict(): void
    {
        $order1 = $this->createPaidOrder(500_000, 'ret:checkout:conf-s1');
        $order2 = $this->createPaidOrder(600_000, 'ret:checkout:conf-s2');
        $order1->update(['status' => 'DELIVERED']);
        $order2->update(['status' => 'DELIVERED']);

        $settleAction = app(SettleRetailSellerEscrowAction::class);
        $key = 'ret:settle:conflict-key-01';

        $settleAction->execute(['order_id' => $order1->id, 'idempotency_key' => $key]);

        $this->expectException(IdempotencyConflictException::class);
        $settleAction->execute(['order_id' => $order2->id, 'idempotency_key' => $key]);
    }

    public function test_unverified_kyb_seller_settlement_blocked(): void
    {
        $unverifiedSeller = RetSeller::create([
            'id' => (string) Str::uuid(),
            'party_id' => 'party-unverified-01',
            'seller_name' => 'Shady Electronics',
            'kyb_status' => 'PENDING',
            'commission_override_bps' => 1000,
            'payout_account_id' => 'bank-acc-unv',
        ]);

        $action = app(CheckoutOmnichannelOrderAction::class);
        $order = $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $unverifiedSeller->id,
            'items' => [
                ['sku' => 'SKU-UNV-01', 'quantity' => 1, 'unit_price_minor' => 200_000],
            ],
            'idempotency_key' => 'ret:checkout:unv-01',
        ]);
        $order->update(['status' => 'DELIVERED']);

        $settleAction = app(SettleRetailSellerEscrowAction::class);

        $this->expectException(SellerNotEligibleException::class);
        $settleAction->execute([
            'order_id' => $order->id,
            'idempotency_key' => 'ret:settle:unv-01',
        ]);
    }

    public function test_refund_before_settlement_releases_escrow_back_to_customer(): void
    {
        $order = $this->createPaidOrder(400_000, 'ret:checkout:ref-pre-01');

        $refundAction = app(ProcessOrderRefundAction::class);
        $refund = $refundAction->execute([
            'order_id' => $order->id,
            'reason' => 'Customer cancelled before fulfillment',
            'idempotency_key' => 'ret:refund:pre-01',
        ]);

        $order->refresh();
        $this->assertEquals('REFUNDED', $order->status);

        // Escrow entry debited (reversed)
        $escrowDebit = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'escrow:payment:IDR'))
            ->where('amount', '<', 0)
            ->latest('id')
            ->first();
        $this->assertNotNull($escrowDebit);
        $this->assertEquals(-400_000, (int) $escrowDebit->amount);
    }

    public function test_refund_after_settlement_debits_seller_ap_and_reverses_commission(): void
    {
        $order = $this->createPaidOrder(1_000_000, 'ret:checkout:ref-post-01');
        $order->update(['status' => 'DELIVERED']);

        $settleAction = app(SettleRetailSellerEscrowAction::class);
        $settleAction->execute([
            'order_id' => $order->id,
            'idempotency_key' => 'ret:settle:post-ref-01',
        ]);

        $refundAction = app(ProcessOrderRefundAction::class);
        $refund = $refundAction->execute([
            'order_id' => $order->id,
            'reason' => 'Defective item returned after delivery',
            'idempotency_key' => 'ret:refund:post-01',
        ]);

        $order->refresh();
        $this->assertEquals('REFUNDED', $order->status);

        // Reversal of commission (debit commission revenue)
        $commissionReversal = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ret:commission_revenue:IDR'))
            ->where('amount', '<', 0)
            ->latest('id')
            ->first();
        $this->assertNotNull($commissionReversal);
        $this->assertEquals(-100_000, (int) $commissionReversal->amount);

        // Reversal of seller AP (debit seller AP)
        $sellerApReversal = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', "ap:ret:seller:{$this->seller->party_id}:IDR"))
            ->where('amount', '<', 0)
            ->latest('id')
            ->first();
        $this->assertNotNull($sellerApReversal);
        $this->assertEquals(-900_000, (int) $sellerApReversal->amount);
    }

    public function test_partial_refund_prorates_commission_and_seller_ap(): void
    {
        $order = $this->createPaidOrder(1_000_000, 'ret:checkout:ref-part-01');
        $order->update(['status' => 'DELIVERED']);

        $settleAction = app(SettleRetailSellerEscrowAction::class);
        $settleAction->execute([
            'order_id' => $order->id,
            'idempotency_key' => 'ret:settle:part-ref-01',
        ]);

        $refundAction = app(ProcessOrderRefundAction::class);
        $refund = $refundAction->execute([
            'order_id' => $order->id,
            'amount_minor' => 500_000, // 50% partial refund
            'reason' => 'Partial item damage compensation',
            'idempotency_key' => 'ret:refund:part-01',
        ]);

        $this->assertEquals(500_000, $refund['refund_amount_minor']);
        $this->assertEquals(50_000, $refund['commission_refund_minor']);
        $this->assertEquals(450_000, $refund['seller_ap_deduction_minor']);
    }

    public function test_settlement_creates_deterministic_ledger_transaction_key(): void
    {
        $order = $this->createPaidOrder(750_000, 'ret:checkout:dtx-key-01');
        $order->update(['status' => 'DELIVERED']);

        $settleAction = app(SettleRetailSellerEscrowAction::class);
        $settlement = $settleAction->execute([
            'order_id' => $order->id,
            'idempotency_key' => 'ret:settle:det-key-01',
        ]);

        $this->assertDatabaseHas('bank_ledger_transactions', [
            'idempotency_key' => 'ret:settle:det-key-01',
            'reference_type' => 'RET_SETTLEMENT',
        ]);
    }

    public function test_unsettled_order_in_transit_cannot_be_settled(): void
    {
        $order = $this->createPaidOrder(200_000, 'ret:checkout:in-transit-01');
        $order->update(['status' => 'SHIPPED']);

        $settleAction = app(SettleRetailSellerEscrowAction::class);

        $this->expectException(\DomainException::class);
        $settleAction->execute([
            'order_id' => $order->id,
            'idempotency_key' => 'ret:settle:early-01',
        ]);
    }
}
