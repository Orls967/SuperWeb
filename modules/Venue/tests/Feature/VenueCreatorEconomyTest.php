<?php

namespace Modules\Venue\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Venue\Application\Services\VenueCreatorEconomyService;
use Modules\Venue\Domain\Models\Creator;
use RuntimeException;
use Tests\TestCase;

class VenueCreatorEconomyTest extends TestCase
{
    use RefreshDatabase;

    protected VenueCreatorEconomyService $service;

    protected Creator $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VenueCreatorEconomyService::class);

        $this->creator = $this->service->registerCreator('DJ ElectroNusantara', 'MELODIC_TECHNO');

        // Register venue ledger accounts
        $accounts = [
            'ven:streaming_receivable:IDR' => 'asset',
            'ven:platform_streaming_revenue:IDR' => 'revenue',
            'ven:creator_held_royalty_escrow:IDR' => 'liability',
            'ven:creator_payable:IDR' => 'liability',
            'ven:merch_sales_receivable:IDR' => 'asset',
            'ven:platform_merch_revenue:IDR' => 'revenue',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Venue {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_115_2_and_115_6_a_and_b_streaming_royalty_hold_and_payout(): void
    {
        $contract = $this->service->createRightsContract($this->creator, 70.0, 14);

        // 1. Accrue stream revenue: 10,000,000 IDR -> 70% artist royalty = 7,000,000 IDR
        $royaltyIdr = $this->service->accrueStreamRevenue($contract, 10_000_000);
        $this->assertEquals(7_000_000, $royaltyIdr);

        $escrow = LedgerAccount::where('code', 'ven:creator_held_royalty_escrow:IDR')->first();
        $this->assertEquals('-7000000', (string) $escrow->cached_balance);

        // 2. Attempt payout before window passes must fail
        try {
            $this->service->releaseRoyaltyPayout($contract, false);
            $this->fail('Expected hold window exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Payout hold active', $e->getMessage());
        }

        // 3. Release payout after window passes
        $paid = $this->service->releaseRoyaltyPayout($contract, true);
        $this->assertEquals(7_000_000, $paid);

        $escrow->refresh();
        $payable = LedgerAccount::where('code', 'ven:creator_payable:IDR')->first();

        $this->assertEquals('0', (string) $escrow->cached_balance);
        $this->assertEquals('-7000000', (string) $payable->cached_balance);
    }

    public function test_115_5_and_115_6_d_merch_split_sum_equals_total_sale(): void
    {
        // Total merch price: 350,000 IDR, 60% artist (210,000 IDR), 40% platform (140,000 IDR)
        $sale = $this->service->recordMerchSale(
            creator: $this->creator,
            itemName: 'Festival Oversized Tour Hoodie',
            totalSalePriceIdr: 350_000,
            artistSplitPercent: 60.0
        );

        $this->assertEquals(210_000, $sale->artist_share_idr);
        $this->assertEquals(140_000, $sale->platform_share_idr);
        $this->assertEquals(350_000, $sale->artist_share_idr + $sale->platform_share_idr);

        $creatorPayable = LedgerAccount::where('code', 'ven:creator_payable:IDR')->first();
        $platformRev = LedgerAccount::where('code', 'ven:platform_merch_revenue:IDR')->first();

        $this->assertEquals('-210000', (string) $creatorPayable->cached_balance);
        $this->assertEquals('-140000', (string) $platformRev->cached_balance);
    }
}
