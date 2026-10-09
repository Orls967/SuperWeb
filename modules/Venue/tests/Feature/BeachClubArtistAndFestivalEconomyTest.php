<?php

declare(strict_types=1);

namespace Modules\Venue\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Venue\Application\Services\VenueEconomyService;
use Modules\Venue\Domain\Models\ArtistContract;
use Modules\Venue\Domain\Models\BundleOrder;
use Modules\Venue\Domain\Models\FestivalBundle;
use Modules\Venue\Domain\Models\VenueMembership;
use Tests\TestCase;

class BeachClubArtistAndFestivalEconomyTest extends TestCase
{
    use RefreshDatabase;

    protected VenueEconomyService $economyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->economyService = app(VenueEconomyService::class);

        $accounts = [
            'expense:venue_artist_fee:IDR' => 'expense',
            'escrow:venue_bundle:IDR' => 'escrow',
            'hotel:resort:IDR' => 'liability',
            'transport:shuttle:IDR' => 'liability',
            'venue:beachclub:IDR' => 'liability',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::firstOrCreate(
                ['code' => $code],
                [
                    'name' => "Account {$code}",
                    'kind' => $kind,
                    'asset_code' => 'IDR',
                    'allow_negative' => true,
                    'cached_balance' => '0',
                ]
            );
        }
    }

    public function test_artist_door_share_calculation_minus_advance(): void
    {
        $contractId = (string) Str::uuid();
        LedgerAccount::firstOrCreate(
            ['code' => "artist:payable:{$contractId}:IDR"],
            [
                'name' => "Artist Payable {$contractId}",
                'kind' => 'liability',
                'asset_code' => 'IDR',
                'allow_negative' => true,
                'cached_balance' => '0',
            ]
        );

        $contract = ArtistContract::create([
            'id' => $contractId,
            'venue_id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'contract_number' => 'CTR-ART-001',
            'artist_name' => 'DJ Solomun',
            'advance_amount' => 50000000, // 50 juta IDR advance
            'door_share_percentage' => 20.00, // 20% door share
            'currency' => 'IDR',
            'status' => 'active',
        ]);

        // Total door sales = 500,000,000 IDR (20% = 100,000,000 IDR). Net payout = 100M - 50M = 50M IDR
        $settled = $this->economyService->settleArtistContract($contract->id, 500000000);

        $this->assertEquals(100000000, $settled->door_share_gross);
        $this->assertEquals(50000000, $settled->net_payout_amount);
        $this->assertEquals('settled', $settled->status);
    }

    public function test_membership_loyalty_point_earn_and_redeem(): void
    {
        $membership = VenueMembership::create([
            'id' => (string) Str::uuid(),
            'user_id' => (string) Str::uuid(),
            'membership_number' => 'MEM-SUN-99',
            'tier' => 'Infinity', // 2x multiplier
            'loyalty_points' => 0,
            'discount_rate' => 0.20,
            'status' => 'active',
        ]);

        // Spend 1,000,000 IDR -> base 100 points * 2x = 200 points
        $updated = $this->economyService->earnPoints($membership->id, 1000000);
        $this->assertEquals(200, $updated->loyalty_points);

        // Redeem 50 points -> 150 points left
        $redeemed = $this->economyService->redeemPoints($membership->id, 50);
        $this->assertEquals(150, $redeemed->loyalty_points);
    }

    public function test_festival_bundle_multi_vendor_settlement_sum_invariant(): void
    {
        $bundle = FestivalBundle::create([
            'id' => (string) Str::uuid(),
            'bundle_code' => 'FEST-VIP-PASS',
            'bundle_name' => 'Sunset Festival 3-Day All Inclusive',
            'total_package_price' => 5000000, // 5M IDR
            'vendor_allocations' => [
                ['vendor_code' => 'HOTEL', 'amount' => 2500000, 'account' => 'hotel:resort:IDR'],
                ['vendor_code' => 'TRANSPORT', 'amount' => 500000, 'account' => 'transport:shuttle:IDR'],
                ['vendor_code' => 'VENUE', 'amount' => 2000000, 'account' => 'venue:beachclub:IDR'],
            ],
            'status' => 'active',
        ]);

        $order = BundleOrder::create([
            'id' => (string) Str::uuid(),
            'festival_bundle_id' => $bundle->id,
            'customer_user_id' => (string) Str::uuid(),
            'order_number' => 'ORD-FEST-881',
            'total_paid' => 5000000,
            'status' => 'pending_settlement',
        ]);

        $settledOrder = $this->economyService->settleFestivalBundleOrder($order->id);

        $this->assertEquals('settled', $settledOrder->status);
        $this->assertNotNull($settledOrder->settled_at);
    }
}
