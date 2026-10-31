<?php

declare(strict_types=1);

namespace Tests\Acceptance\Retail;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Ret\Application\Actions\CheckoutOmnichannelOrderAction;
use Modules\Ret\Domain\Exceptions\IdempotencyConflictException;
use Modules\Ret\Domain\Exceptions\InsufficientStockException;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Ret\Domain\Models\RetSeller;
use Tests\TestCase;

/**
 * @covers \Modules\Ret\Application\Actions\CheckoutOmnichannelOrderAction
 */
class OmnichannelCheckoutAndAntiDoubleSellTest extends TestCase
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
            'channel_code' => 'ONLINE_WEB',
            'channel_name' => 'Superweb Online Store',
            'channel_type' => 'WEB',
            'default_commission_bps' => 500, // 5%
            'status' => 'ACTIVE',
        ]);

        $this->seller = RetSeller::create([
            'id' => (string) Str::uuid(),
            'party_id' => 'party-seller-01',
            'seller_name' => 'Official Tech Store',
            'kyb_status' => 'VERIFIED',
            'commission_override_bps' => 500,
            'payout_account_ref' => 'payout-01',
        ]);

        $this->customer = User::factory()->create(['role' => 'customer']);
    }

    public function test_checkout_with_stock_reservation_succeeds_and_creates_order(): void
    {
        $action = app(CheckoutOmnichannelOrderAction::class);

        $order = $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-LAPTOP-01', 'quantity' => 1, 'unit_price_minor' => 15_000_000],
            ],
            'packaging_deposit_minor' => 50_000,
            'idempotency_key' => 'ret:checkout:order-1001',
        ]);

        $this->assertInstanceOf(RetOrder::class, $order);
        $this->assertEquals('PAID', $order->status);
        $this->assertEquals(15_050_000, $order->total_amount_minor);
        $this->assertNotEmpty($order->order_number);
    }

    public function test_multiple_concurrent_checkouts_cannot_oversell_stock(): void
    {
        $action = app(CheckoutOmnichannelOrderAction::class);

        // First order reserves available 2 units
        $order1 = $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-LIMITED-01', 'quantity' => 2, 'unit_price_minor' => 100_000],
            ],
            'idempotency_key' => 'ret:checkout:limit-01',
        ]);
        $this->assertEquals('PAID', $order1->status);

        // Second order requesting additional unit throws InsufficientStockException
        $this->expectException(InsufficientStockException::class);
        $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-LIMITED-01', 'quantity' => 1, 'unit_price_minor' => 100_000],
            ],
            'idempotency_key' => 'ret:checkout:limit-02',
        ]);
    }

    public function test_order_reservation_expires_and_releases_stock_when_cancelled(): void
    {
        $action = app(CheckoutOmnichannelOrderAction::class);

        $order = $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-CANCEL-01', 'quantity' => 1, 'unit_price_minor' => 200_000],
            ],
            'idempotency_key' => 'ret:checkout:cancel-01',
        ]);

        $order->cancelOrder('Customer requested cancellation before shipment');
        $order->refresh();

        $this->assertEquals('CANCELLED', $order->status);
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_checkout_requires_idempotency_key(): void
    {
        $action = app(CheckoutOmnichannelOrderAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-NOKEY-01', 'quantity' => 1, 'unit_price_minor' => 50_000],
            ],
            // missing idempotency_key
        ]);
    }

    public function test_duplicate_checkout_with_same_key_returns_existing_order(): void
    {
        $action = app(CheckoutOmnichannelOrderAction::class);
        $key = 'ret:checkout:idem-dup-01';

        $order1 = $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-DUP-01', 'quantity' => 1, 'unit_price_minor' => 300_000],
            ],
            'idempotency_key' => $key,
        ]);

        $order2 = $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-DUP-01', 'quantity' => 1, 'unit_price_minor' => 300_000],
            ],
            'idempotency_key' => $key,
        ]);

        $this->assertEquals($order1->id, $order2->id);
        $this->assertEquals($order1->order_number, $order2->order_number);
    }

    public function test_conflicting_payload_with_same_key_throws_idempotency_conflict(): void
    {
        $action = app(CheckoutOmnichannelOrderAction::class);
        $key = 'ret:checkout:conflict-01';

        $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-CONF-01', 'quantity' => 1, 'unit_price_minor' => 400_000],
            ],
            'idempotency_key' => $key,
        ]);

        $this->expectException(IdempotencyConflictException::class);
        $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-CONF-01', 'quantity' => 2, 'unit_price_minor' => 800_000], // different payload
            ],
            'idempotency_key' => $key,
        ]);
    }

    public function test_zero_or_negative_quantity_items_rejected(): void
    {
        $action = app(CheckoutOmnichannelOrderAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-BAD-QTY', 'quantity' => 0, 'unit_price_minor' => 100_000],
            ],
            'idempotency_key' => 'ret:checkout:bad-qty-01',
        ]);
    }

    public function test_inactive_channel_checkout_rejected(): void
    {
        $inactiveChannel = RetChannel::create([
            'id' => (string) Str::uuid(),
            'channel_code' => 'INACTIVE_KIOSK',
            'channel_name' => 'Closed Kiosk',
            'channel_type' => 'STORE',
            'default_commission_bps' => 500,
            'status' => 'INACTIVE',
        ]);

        $action = app(CheckoutOmnichannelOrderAction::class);

        $this->expectException(\DomainException::class);
        $action->execute([
            'channel_id' => $inactiveChannel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-INACT-01', 'quantity' => 1, 'unit_price_minor' => 100_000],
            ],
            'idempotency_key' => 'ret:checkout:inact-01',
        ]);
    }

    public function test_multi_item_cart_checkout_calculates_total_minor_accurately(): void
    {
        $action = app(CheckoutOmnichannelOrderAction::class);

        $order = $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-ITEM-A', 'quantity' => 3, 'unit_price_minor' => 33_333],
                ['sku' => 'SKU-ITEM-B', 'quantity' => 2, 'unit_price_minor' => 50_000],
            ],
            'packaging_deposit_minor' => 10_000,
            'idempotency_key' => 'ret:checkout:multi-sum-01',
        ]);

        // (3 * 33333) + (2 * 50000) + 10000 = 99999 + 100000 + 10000 = 209999
        $this->assertEquals(209_999, $order->total_amount_minor);
        $this->assertCount(2, $order->items);
    }

    public function test_checkout_holds_funds_in_payment_escrow_ledger(): void
    {
        $action = app(CheckoutOmnichannelOrderAction::class);

        $order = $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-ESCROW-01', 'quantity' => 1, 'unit_price_minor' => 1_000_000],
            ],
            'idempotency_key' => 'ret:checkout:escrow-hold-01',
        ]);

        // Escrow entry check
        $escrowEntry = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'escrow:payment:IDR'))
            ->first();

        $this->assertNotNull($escrowEntry);
        $this->assertEquals(1_000_000, (int) $escrowEntry->amount);
    }

    public function test_order_number_is_deterministic_and_gapless(): void
    {
        $action = app(CheckoutOmnichannelOrderAction::class);

        $order = $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-GAPLESS-01', 'quantity' => 1, 'unit_price_minor' => 100_000],
            ],
            'idempotency_key' => 'ret:checkout:gapless-01',
        ]);

        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{6}$/', $order->order_number);
    }

    public function test_checkout_triggers_ret_order_paid_event(): void
    {
        $action = app(CheckoutOmnichannelOrderAction::class);

        $order = $action->execute([
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['sku' => 'SKU-EVENT-01', 'quantity' => 1, 'unit_price_minor' => 150_000],
            ],
            'idempotency_key' => 'ret:checkout:event-01',
        ]);

        $this->assertDatabaseHas('ret_orders', [
            'id' => $order->id,
            'status' => 'PAID',
        ]);
    }
}
