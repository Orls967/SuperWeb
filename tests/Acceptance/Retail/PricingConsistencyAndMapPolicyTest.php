<?php

declare(strict_types=1);

namespace Tests\Acceptance\Retail;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Ret\Application\Actions\ApplyCrossChannelCouponAction;
use Modules\Ret\Application\Actions\CreateOrUpdateListingAction;
use Modules\Ret\Application\Actions\ExecuteExpiryMarkdownPricingAction;
use Modules\Ret\Domain\Exceptions\CouponAlreadyRedeemedException;
use Modules\Ret\Domain\Exceptions\MapPriceViolationException;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetCouponRedemption;
use Modules\Ret\Domain\Models\RetListing;
use Modules\Ret\Domain\Models\RetSeller;
use Tests\TestCase;

class PricingConsistencyAndMapPolicyTest extends TestCase
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
            'channel_code' => 'WEB_MARKET',
            'channel_name' => 'Web Marketplace',
            'channel_type' => 'MARKETPLACE_3P',
            'default_commission_bps' => 1000,
            'status' => 'ACTIVE',
        ]);

        $this->seller = RetSeller::create([
            'id' => (string) Str::uuid(),
            'party_id' => 'party-seller-map',
            'seller_name' => 'Authorized Gadget Store',
            'kyb_status' => 'VERIFIED',
            'commission_override_bps' => 1000,
            'payout_account_id' => 'payout-map',
        ]);

        $this->customer = User::factory()->create(['role' => 'customer']);
    }

    public function test_listing_price_equal_or_above_map_succeeds(): void
    {
        $action = app(CreateOrUpdateListingAction::class);

        // MAP is 500_000 IDR, Listing at 550_000 IDR
        $listing = $action->execute([
            'seller_id' => $this->seller->id,
            'channel_id' => $this->channel->id,
            'sku' => 'SKU-PHONE-01',
            'title' => 'Smart Phone Pro 128GB',
            'price_minor' => 550_000,
            'idempotency_key' => 'ret:list:map-ok-01',
        ]);

        $this->assertInstanceOf(RetListing::class, $listing);
        $this->assertEquals(550_000, $listing->price_minor);
        $this->assertTrue($listing->is_active);
    }

    public function test_listing_below_map_policy_rejected_with_violation(): void
    {
        $action = app(CreateOrUpdateListingAction::class);

        // MAP is 500_000 IDR, attempting to list at 450_000 IDR violates MAP policy
        $this->expectException(MapPriceViolationException::class);
        $action->execute([
            'seller_id' => $this->seller->id,
            'channel_id' => $this->channel->id,
            'sku' => 'SKU-PHONE-01',
            'title' => 'Dumping Priced Phone',
            'price_minor' => 450_000,
            'idempotency_key' => 'ret:list:map-viol-01',
        ]);
    }

    public function test_seller_with_repeated_map_violations_is_flagged(): void
    {
        $action = app(CreateOrUpdateListingAction::class);

        for ($i = 0; $i < 3; $i++) {
            try {
                $action->execute([
                    'seller_id' => $this->seller->id,
                    'channel_id' => $this->channel->id,
                    'sku' => 'SKU-PHONE-01',
                    'title' => "Violation Attempt {$i}",
                    'price_minor' => 300_000,
                    'idempotency_key' => "ret:list:map-viol-rep-{$i}",
                ]);
            } catch (MapPriceViolationException) {
                // Expected violation caught
            }
        }

        $this->seller->refresh();
        $this->assertGreaterThanOrEqual(3, $this->seller->map_violation_count);
    }

    public function test_dynamic_expiry_markdown_respects_absolute_floor(): void
    {
        $action = app(ExecuteExpiryMarkdownPricingAction::class);

        // Original price 100_000, 1 day left would discount 70% to 30_000,
        // but floor is set at 35_000 -> price must be clamped to 35_000
        $res = $action->execute([
            'sku' => 'SKU-DAIRY-YOGURT',
            'original_price_minor' => 100_000,
            'days_remaining' => 1,
            'floor_price_minor' => 35_000,
            'idempotency_key' => 'ret:mark:floor-01',
        ]);

        $this->assertEquals(35_000, $res['discounted_price_minor']);
    }

    public function test_cross_channel_coupon_applied_successfully(): void
    {
        $action = app(ApplyCrossChannelCouponAction::class);

        $redemption = $action->execute([
            'coupon_code' => 'SUPERPROMO10',
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'order_amount_minor' => 200_000,
            'idempotency_key' => 'ret:coupon:apply-01',
        ]);

        $this->assertInstanceOf(RetCouponRedemption::class, $redemption);
        $this->assertEquals(20_000, $redemption->discount_minor); // 10% of 200k = 20k
    }

    public function test_cross_channel_coupon_cannot_be_redeemed_twice_by_same_customer(): void
    {
        $action = app(ApplyCrossChannelCouponAction::class);

        $action->execute([
            'coupon_code' => 'SINGLEUSE50',
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'order_amount_minor' => 500_000,
            'idempotency_key' => 'ret:coupon:first-01',
        ]);

        $this->expectException(CouponAlreadyRedeemedException::class);
        $action->execute([
            'coupon_code' => 'SINGLEUSE50',
            'channel_id' => $this->channel->id,
            'customer_id' => $this->customer->id,
            'order_amount_minor' => 300_000,
            'idempotency_key' => 'ret:coupon:second-01',
        ]);
    }

    public function test_listing_requires_idempotency_key(): void
    {
        $action = app(CreateOrUpdateListingAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $action->execute([
            'seller_id' => $this->seller->id,
            'channel_id' => $this->channel->id,
            'sku' => 'SKU-NO-KEY',
            'title' => 'No Key Listing',
            'price_minor' => 600_000,
            // missing key
        ]);
    }

    public function test_duplicate_listing_update_with_same_key_is_idempotent(): void
    {
        $action = app(CreateOrUpdateListingAction::class);
        $key = 'ret:list:dup-01';

        $l1 = $action->execute([
            'seller_id' => $this->seller->id,
            'channel_id' => $this->channel->id,
            'sku' => 'SKU-IDEM-01',
            'title' => 'Idempotent Item',
            'price_minor' => 700_000,
            'idempotency_key' => $key,
        ]);

        $l2 = $action->execute([
            'seller_id' => $this->seller->id,
            'channel_id' => $this->channel->id,
            'sku' => 'SKU-IDEM-01',
            'title' => 'Idempotent Item',
            'price_minor' => 700_000,
            'idempotency_key' => $key,
        ]);

        $this->assertEquals($l1->id, $l2->id);
    }
}
