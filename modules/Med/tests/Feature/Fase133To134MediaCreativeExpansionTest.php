<?php

declare(strict_types=1);

namespace Modules\Med\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Med\Application\Actions\AttributeOmniAdSponsorshipConversionRoasAction;
use Modules\Med\Application\Actions\SettleMultiPlatformContentSyndicationWaterfallAction;
use Tests\TestCase;

class Fase133To134MediaCreativeExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_fase_133_waterfall_talent_royalty_distribution_with_last_penny_rounding(): void
    {
        $action = app(SettleMultiPlatformContentSyndicationWaterfallAction::class);

        // Net revenue: 100M Box Office + 50M Streaming = 150M. Royalty pool: 10% = 15,000,000.
        // Talents: Director (33%), Producer (33%), Lead Actor (34%).
        // 33% of 15M = 4,950,000. 33% of 15M = 4,950,000. Total so far = 9,900,000.
        // Last row absorbs remaining: 15,000,000 - 9,900,000 = 5,100,000.
        $params = [
            'syndication_code' => 'SYN-FILM-001',
            'project_code' => 'PRJ-ACTION-HERO',
            'target_territory' => 'MY',
            'existing_licensed_territories' => ['SG', 'TH'],
            'box_office_revenue_minor' => 100000000,
            'streaming_revenue_minor' => 50000000,
            'royalty_pool_pct' => 10,
            'talent_shares' => [
                ['talent_name' => 'Director Christopher', 'role' => 'DIRECTOR', 'share_pct' => 33],
                ['talent_name' => 'Producer Emma', 'role' => 'PRODUCER', 'share_pct' => 33],
                ['talent_name' => 'Actor Tom', 'role' => 'LEAD_ACTOR', 'share_pct' => 34],
            ],
        ];

        $res = $action->execute($params);

        $this->assertEquals(150000000, $res['total_audited_revenue_minor']);
        $this->assertEquals(15000000, $res['total_royalty_pool_minor']);
        $this->assertCount(3, $res['talent_payouts']);
        $this->assertEquals(4950000, $res['talent_payouts'][0]['payout_minor']);
        $this->assertEquals(4950000, $res['talent_payouts'][1]['payout_minor']);
        $this->assertEquals(5100000, $res['talent_payouts'][2]['payout_minor']);

        // Verify that sum of payouts exactly equals royalty pool
        $sumPayouts = array_sum(array_column($res['talent_payouts'], 'payout_minor'));
        $this->assertEquals(15000000, $sumPayouts);

        $tx = LedgerTransaction::where('reference_id', 'SYN-FILM-001')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));
    }

    public function test_fase_133_rejects_territorial_ip_licensing_overlap(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Territorial IP licensing overlap rejected');

        $action = app(SettleMultiPlatformContentSyndicationWaterfallAction::class);
        $action->execute([
            'syndication_code' => 'SYN-ERR-01',
            'project_code' => 'PRJ-SERIES-02',
            'target_territory' => 'ID',
            'existing_licensed_territories' => ['ID', 'SG'], // ID already licensed!
        ]);
    }

    public function test_fase_134_ad_inventory_booking_enforces_yield_floor_pricing(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('below minimum floor price');

        $action = app(AttributeOmniAdSponsorshipConversionRoasAction::class);
        $action->execute([
            'campaign_code' => 'AD-FLOOR-FAIL',
            'bid_cpm_minor' => 20000, // Below floor 35,000
            'floor_price_cpm_minor' => 35000,
        ]);
    }

    public function test_fase_134_reconciles_verified_impressions_and_multi_party_agency_split(): void
    {
        $action = app(AttributeOmniAdSponsorshipConversionRoasAction::class);

        // 200,000 claimed vs 180,000 verified @ 50,000 CPM = 9,000,000 billed.
        // Agency (15%) = 1,350,000. Publisher (70%) = 6,300,000. Platform (15%) = 1,350,000.
        $params = [
            'campaign_code' => 'AD-CAMPAIGN-001',
            'bid_cpm_minor' => 50000,
            'floor_price_cpm_minor' => 30000,
            'impressions_claimed' => 200000,
            'impressions_verified' => 180000,
            'agency_commission_pct' => 15,
            'publisher_share_pct' => 70,
            'attributed_conversions' => 500,
            'attributed_revenue_minor' => 45000000,
        ];

        $res = $action->execute($params);

        $this->assertEquals(9000000, $res['total_billed_minor']);
        $this->assertEquals(1350000, $res['agency_commission_minor']);
        $this->assertEquals(6300000, $res['publisher_payable_minor']);
        $this->assertEquals(1350000, $res['platform_net_fee_minor']);
        $this->assertEquals(50, $res['roas_multiple_basis']); // 45M / 9M = 5.0x (50)

        $tx = LedgerTransaction::where('reference_id', 'AD-CAMPAIGN-001')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));
    }
}
