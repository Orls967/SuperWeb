<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Med\Application\Services\DistributionAndAdvertisingService;
use Modules\Med\Domain\Models\MedAdCampaign;
use Modules\Med\Domain\Models\MedSponsorshipPackage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $accounts = [
        'med:ad_receivable:IDR' => 'asset',
        'med:ad_publisher_payable:IDR' => 'liability',
        'med:ad_platform_revenue:IDR' => 'revenue',
        'med:agency_commission_payable:IDR' => 'liability',
    ];

    foreach ($accounts as $code => $kind) {
        LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'kind' => $kind,
                'asset_code' => 'IDR',
                'allow_negative' => true,
                'cached_balance' => '0',
                'name' => "Media {$code}",
            ]
        );
    }
});

test('(a) yield management menolak harga di bawah floor CPM', function () {
    $service = app(DistributionAndAdvertisingService::class);

    $channel = $service->createChannel([
        'name' => 'Mall LED Billboard Network',
        'channel_type' => 'DIGITAL_OOH',
        'revenue_share_pct' => 70.0,
    ]);

    expect(fn () => $service->bookCampaign([
        'advertiser_entity_id' => 'BRAND-BEVERAGE-01',
        'channel_id' => $channel->id,
        'target_impressions' => 1000000,
        'floor_cpm_minor' => 7500000, // floor 75k IDR
        'actual_cpm_minor' => 6000000, // bid 60k IDR -> below floor!
    ]))->toThrow(RuntimeException::class, 'Yield management rejection');
});

test('(b), (c), (d) impressions terverifikasi menyesuaikan tagihan, split multi-pihak seimbang, komisi agency akurat', function () {
    $service = app(DistributionAndAdvertisingService::class);

    $channel = $service->createChannel([
        'name' => 'SuperApp In-Stream Video',
        'channel_type' => 'APP_STREAMING',
        'revenue_share_pct' => 80.0, // 80% to publisher, 20% to platform
    ]);

    $campaign = $service->bookCampaign([
        'advertiser_entity_id' => 'AUTOMOTIVE-CORP',
        'agency_entity_id' => 'AGENCY-CREATIVE-LEAD',
        'agency_commission_pct' => 15.0, // 15% agency commission
        'channel_id' => $channel->id,
        'target_impressions' => 500000, // target 500k
        'floor_cpm_minor' => 5000000,
        'actual_cpm_minor' => 10000000, // 100k IDR per 1,000 impressions
    ]);

    expect($campaign)->toBeInstanceOf(MedAdCampaign::class);

    // Verified impressions only reached 400k (sensor footfall / telemetry count)
    $settled = $service->settleCampaignImpressions($campaign->id, 400000);

    // 400k impressions / 1000 = 400 units * 10,000,000 = 4,000,000,000 minor total spend
    expect($settled->total_spend_minor)->toBe(4000000000);

    // (d) Agency commission: 15% of 4B = 600,000,000 minor
    expect($settled->agency_commission_minor)->toBe(600000000);

    // Remaining for pub + plat: 4B - 600M = 3.4B minor
    // Publisher 80% of 3.4B = 2,720,000,000 minor
    expect($settled->publisher_share_minor)->toBe(2720000000);

    // Platform share 20% of 3.4B = 680,000,000 minor
    expect($settled->platform_share_minor)->toBe(680000000);

    // (c) Split sum matches total spend exactly
    $sumSplits = $settled->agency_commission_minor + $settled->publisher_share_minor + $settled->platform_share_minor;
    expect($sumSplits)->toBe($settled->total_spend_minor);
});

test('(e) sponsorship cross-lini bundling terdaftar dengan channel paket', function () {
    $service = app(DistributionAndAdvertisingService::class);

    $sponsorship = $service->createSponsorshipPackage([
        'sponsor_brand_id' => 'TELCO-GIANT-CORP',
        'event_or_entity_ref' => 'ESPORTS-CHAMPIONSHIP-2026',
        'package_tier' => 'TITLE_SPONSOR',
        'total_sponsorship_minor' => 2500000000000,
        'bundled_channels' => ['APP_STREAMING', 'DIGITAL_OOH', 'VENUE_NAMING_RIGHTS'],
    ]);

    expect($sponsorship)->toBeInstanceOf(MedSponsorshipPackage::class)
        ->and($sponsorship->package_tier)->toBe('TITLE_SPONSOR')
        ->and($sponsorship->bundled_channels)->toContain('VENUE_NAMING_RIGHTS');
});
