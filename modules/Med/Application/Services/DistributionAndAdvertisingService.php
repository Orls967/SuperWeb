<?php

declare(strict_types=1);

namespace Modules\Med\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Med\Domain\Models\MedAdCampaign;
use Modules\Med\Domain\Models\MedDistributionChannel;
use Modules\Med\Domain\Models\MedSponsorshipPackage;
use RuntimeException;

class DistributionAndAdvertisingService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function createChannel(array $params): MedDistributionChannel
    {
        return MedDistributionChannel::create([
            'id' => (string) Str::uuid(),
            'channel_code' => $params['channel_code'] ?? 'CH-'.strtoupper(Str::random(6)),
            'name' => $params['name'],
            'channel_type' => $params['channel_type'],
            'revenue_share_pct' => (float) ($params['revenue_share_pct'] ?? 70.0),
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * 134.2 Yield Management & Campaign booking.
     * Test (a): actual CPM cannot be below floor CPM.
     */
    public function bookCampaign(array $params): MedAdCampaign
    {
        $floorCpm = (int) $params['floor_cpm_minor'];
        $actualCpm = (int) $params['actual_cpm_minor'];

        if ($actualCpm < $floorCpm) {
            throw new RuntimeException("Yield management rejection: actual CPM ({$actualCpm}) is below floor CPM ({$floorCpm}).");
        }

        return MedAdCampaign::create([
            'id' => (string) Str::uuid(),
            'campaign_code' => $params['campaign_code'] ?? 'CMP-'.strtoupper(Str::random(8)),
            'advertiser_entity_id' => $params['advertiser_entity_id'],
            'agency_entity_id' => $params['agency_entity_id'] ?? null,
            'agency_commission_pct' => (float) ($params['agency_commission_pct'] ?? 0.0),
            'channel_id' => $params['channel_id'],
            'target_impressions' => (int) $params['target_impressions'],
            'verified_impressions' => 0,
            'floor_cpm_minor' => $floorCpm,
            'actual_cpm_minor' => $actualCpm,
            'total_spend_minor' => 0,
            'agency_commission_minor' => 0,
            'publisher_share_minor' => 0,
            'platform_share_minor' => 0,
            'status' => 'SCHEDULED',
        ]);
    }

    /**
     * 134.3 & 134.5 Settle Campaign based on verified impressions.
     * Test (b): verified impressions adjust final billing amount.
     * Test (c): sum of splits (agency + publisher + platform) = total spend.
     * Test (d): agency commission = spend * commission rate.
     */
    public function settleCampaignImpressions(string $campaignId, int $verifiedImpressions): MedAdCampaign
    {
        $campaign = MedAdCampaign::findOrFail($campaignId);
        $channel = MedDistributionChannel::findOrFail($campaign->channel_id);

        return DB::transaction(function () use ($campaign, $channel, $verifiedImpressions) {
            $campaign->verified_impressions = $verifiedImpressions;

            // Total spend = (verifiedImpressions / 1000) * actual_cpm_minor
            $totalSpend = (int) round(($verifiedImpressions / 1000.0) * $campaign->actual_cpm_minor);
            $campaign->total_spend_minor = $totalSpend;

            // Agency commission
            $agencyComm = (int) round(($totalSpend * $campaign->agency_commission_pct) / 100.0);
            $campaign->agency_commission_minor = $agencyComm;

            // Remaining after agency commission is split between publisher & platform
            $netSpend = $totalSpend - $agencyComm;
            $pubShare = (int) round(($netSpend * $channel->revenue_share_pct) / 100.0);
            $platformShare = $netSpend - $pubShare; // exact remainder ensures sum = totalSpend

            $campaign->publisher_share_minor = $pubShare;
            $campaign->platform_share_minor = $platformShare;
            $campaign->status = 'COMPLETED';
            $campaign->save();

            // Balanced multi-party ledger settlement (Sum = 0)
            if ($totalSpend > 0) {
                $entries = [
                    PostingEntryDTO::forCode('med:ad_receivable:IDR', 'IDR', $totalSpend),
                    PostingEntryDTO::forCode('med:ad_publisher_payable:IDR', 'IDR', -$pubShare),
                    PostingEntryDTO::forCode('med:ad_platform_revenue:IDR', 'IDR', -$platformShare),
                ];

                if ($agencyComm > 0) {
                    $entries[] = PostingEntryDTO::forCode('med:agency_commission_payable:IDR', 'IDR', -$agencyComm);
                }

                $this->ledgerService->post(new PostingDTO(
                    type: 'MEDIA_AD_CAMPAIGN_SETTLEMENT',
                    description: "Ad campaign settlement for {$campaign->campaign_code} ({$verifiedImpressions} impressions)",
                    idempotencyKey: 'MED-AD-'.$campaign->campaign_code,
                    entries: $entries,
                    referenceType: 'AD_CAMPAIGN',
                    referenceId: $campaign->campaign_code,
                ));
            }

            return $campaign;
        });
    }

    /**
     * 134.4 Cross-lini Sponsorship Bundling
     */
    public function createSponsorshipPackage(array $params): MedSponsorshipPackage
    {
        return MedSponsorshipPackage::create([
            'id' => (string) Str::uuid(),
            'package_code' => 'SPON-'.strtoupper(Str::random(8)),
            'sponsor_brand_id' => $params['sponsor_brand_id'],
            'event_or_entity_ref' => $params['event_or_entity_ref'],
            'package_tier' => $params['package_tier'],
            'total_sponsorship_minor' => (int) $params['total_sponsorship_minor'],
            'bundled_channels' => $params['bundled_channels'],
            'status' => 'CONFIRMED',
        ]);
    }
}
