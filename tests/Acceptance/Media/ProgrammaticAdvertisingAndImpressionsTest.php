<?php

declare(strict_types=1);

namespace Tests\Acceptance\Media;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Med\Application\Services\AdImpressionService;
use Modules\Med\Domain\Models\MedAdPlacement;
use Modules\Med\Domain\Models\MedDistributionChannel;
use RuntimeException;
use Tests\TestCase;

class ProgrammaticAdvertisingAndImpressionsTest extends TestCase
{
    use RefreshDatabase;

    protected AdImpressionService $adService;

    protected MedDistributionChannel $channel;

    protected MedAdPlacement $placement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->adService = app(AdImpressionService::class);

        $this->channel = MedDistributionChannel::create([
            'id' => (string) Str::uuid(),
            'channel_code' => 'CH-AD-01',
            'name' => 'Mall OOH Network',
            'channel_type' => 'OOH_SCREEN',
            'revenue_share_pct' => 70.0,
            'status' => 'ACTIVE',
        ]);

        $this->placement = MedAdPlacement::create([
            'id' => (string) Str::uuid(),
            'channel_id' => $this->channel->id,
            'placement_code' => 'PLC-ATRIUM-01',
            'location_name' => 'Grand Atrium Screen',
            'floor_cpm_minor' => 60000,
            'is_active' => true,
        ]);
    }

    public function test_campaign_booking_below_floor_cpm_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->adService->bookCampaign([
            'placement_id' => $this->placement->id,
            'advertiser_entity_id' => 'ADV-BEV-01',
            'target_impressions' => 200_000,
            'actual_cpm_minor' => 50000, // Floor is 60,000 -> Rejected!
            'idempotency_key' => 'CMP:REJ:FLOOR',
        ]);
    }

    public function test_campaign_booking_at_or_above_floor_cpm_succeeds(): void
    {
        $campaign = $this->adService->bookCampaign([
            'placement_id' => $this->placement->id,
            'advertiser_entity_id' => 'ADV-BEV-01',
            'target_impressions' => 200_000,
            'actual_cpm_minor' => 65000, // Above floor 60,000 -> Allowed
            'idempotency_key' => 'CMP:ACC:01',
        ]);

        expect($campaign->status)->toBe('SCHEDULED')
            ->and($campaign->actual_cpm_minor)->toBe(65000)
            ->and($campaign->target_impressions)->toBe(200_000);
    }

    public function test_impression_ingestion_stores_append_only_record(): void
    {
        $campaign = $this->adService->bookCampaign([
            'placement_id' => $this->placement->id,
            'advertiser_entity_id' => 'ADV-TECH-01',
            'target_impressions' => 50_000,
            'actual_cpm_minor' => 60000,
            'idempotency_key' => 'CMP:IMP:01',
        ]);

        $impression = $this->adService->ingestImpression([
            'campaign_id' => $campaign->id,
            'impression_hash' => 'IMP-HASH-001',
            'device_ip_hash' => 'IP-HASH-ABC',
            'timestamp_utc' => '2026-11-01 10:00:00',
        ]);

        expect($impression->id)->not->toBeNull()
            ->and($impression->is_verified)->toBeTrue()
            ->and($impression->is_fraud)->toBeFalse();
    }

    public function test_duplicate_impression_hash_is_rejected_or_ignored(): void
    {
        $campaign = $this->adService->bookCampaign([
            'placement_id' => $this->placement->id,
            'advertiser_entity_id' => 'ADV-TECH-01',
            'target_impressions' => 50_000,
            'actual_cpm_minor' => 60000,
            'idempotency_key' => 'CMP:IMP:DUP',
        ]);

        $this->adService->ingestImpression([
            'campaign_id' => $campaign->id,
            'impression_hash' => 'IMP-HASH-DUP-01',
            'device_ip_hash' => 'IP-HASH-ABC',
            'timestamp_utc' => '2026-11-01 10:00:00',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->adService->ingestImpression([
            'campaign_id' => $campaign->id,
            'impression_hash' => 'IMP-HASH-DUP-01',
            'device_ip_hash' => 'IP-HASH-ABC',
            'timestamp_utc' => '2026-11-01 10:00:01',
        ]);
    }

    public function test_fraud_velocity_detection_flags_excessive_rate_from_same_device(): void
    {
        $campaign = $this->adService->bookCampaign([
            'placement_id' => $this->placement->id,
            'advertiser_entity_id' => 'ADV-TECH-01',
            'target_impressions' => 50_000,
            'actual_cpm_minor' => 60000,
            'idempotency_key' => 'CMP:IMP:VEL',
        ]);

        // Ingest 15 impressions within the exact same second from identical device_ip_hash
        $fraudImpression = null;
        for ($i = 1; $i <= 15; $i++) {
            $imp = $this->adService->ingestImpression([
                'campaign_id' => $campaign->id,
                'impression_hash' => "IMP-VEL-{$i}",
                'device_ip_hash' => 'BOT-DEVICE-01',
                'timestamp_utc' => '2026-11-01 10:00:00',
            ]);
            if ($i > 10) {
                $fraudImpression = $imp;
            }
        }

        expect($fraudImpression)->not->toBeNull()
            ->and($fraudImpression->is_fraud)->toBeTrue()
            ->and($fraudImpression->is_verified)->toBeFalse();
    }

    public function test_verified_impressions_adjust_final_billing_spend(): void
    {
        $campaign = $this->adService->bookCampaign([
            'placement_id' => $this->placement->id,
            'advertiser_entity_id' => 'ADV-FIN-01',
            'target_impressions' => 100_000,
            'actual_cpm_minor' => 70000,
            'idempotency_key' => 'CMP:BILL:01',
        ]);

        // Ingest 1,000 verified impressions
        for ($i = 1; $i <= 1000; $i++) {
            $this->adService->ingestImpression([
                'campaign_id' => $campaign->id,
                'impression_hash' => "IMP-BILL-{$i}",
                'device_ip_hash' => "IP-USER-{$i}",
                'timestamp_utc' => '2026-11-01 11:00:00',
            ]);
        }

        $settled = $this->adService->settleCampaign($campaign->id);

        // 1,000 verified impressions @ 70,000 CPM = 70,000 IDR
        expect($settled->verified_impressions)->toBe(1000)
            ->and($settled->total_spend_minor)->toBe(70000);
    }

    public function test_campaign_multi_party_spend_split_exact_sum(): void
    {
        $campaign = $this->adService->bookCampaign([
            'placement_id' => $this->placement->id,
            'advertiser_entity_id' => 'ADV-SPLIT-01',
            'target_impressions' => 500_000,
            'actual_cpm_minor' => 80000,
            'agency_commission_pct' => 15.0,
            'idempotency_key' => 'CMP:SPLIT:01',
        ]);

        for ($i = 1; $i <= 1000; $i++) {
            $this->adService->ingestImpression([
                'campaign_id' => $campaign->id,
                'impression_hash' => "IMP-SPLIT-{$i}",
                'device_ip_hash' => "IP-SPLIT-{$i}",
                'timestamp_utc' => '2026-11-01 12:00:00',
            ]);
        }

        $settled = $this->adService->settleCampaign($campaign->id);
        $totalSpend = $settled->total_spend_minor;
        $agency = $settled->agency_commission_minor;
        $publisher = $settled->publisher_share_minor;
        $platform = $settled->platform_share_minor;

        expect($agency + $publisher + $platform)->toBe($totalSpend);
    }

    public function test_impression_deletion_is_strictly_forbidden(): void
    {
        $campaign = $this->adService->bookCampaign([
            'placement_id' => $this->placement->id,
            'advertiser_entity_id' => 'ADV-DEL-01',
            'target_impressions' => 10_000,
            'actual_cpm_minor' => 60000,
            'idempotency_key' => 'CMP:DEL:01',
        ]);

        $imp = $this->adService->ingestImpression([
            'campaign_id' => $campaign->id,
            'impression_hash' => 'IMP-DEL-01',
            'device_ip_hash' => 'IP-DEL-01',
            'timestamp_utc' => '2026-11-01 13:00:00',
        ]);

        $this->expectException(RuntimeException::class);
        $imp->delete();
    }
}
