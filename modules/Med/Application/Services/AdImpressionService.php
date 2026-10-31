<?php

declare(strict_types=1);

namespace Modules\Med\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Med\Domain\Models\MedAdCampaign;
use Modules\Med\Domain\Models\MedAdImpression;
use Modules\Med\Domain\Models\MedAdPlacement;
use Modules\Med\Domain\Models\MedDistributionChannel;
use RuntimeException;

class AdImpressionService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Book programmatic ad campaign inventory against placement floor pricing.
     *
     * @param array{
     *     placement_id: string,
     *     advertiser_entity_id: string,
     *     target_impressions: int,
     *     actual_cpm_minor: int,
     *     agency_commission_pct?: float,
     *     idempotency_key?: string
     * } $params
     */
    public function bookCampaign(array $params): MedAdCampaign
    {
        $idempotencyKey = $params['idempotency_key'] ?? ('CMP:'.Str::uuid());
        $existing = MedAdCampaign::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $placement = MedAdPlacement::findOrFail($params['placement_id']);
        $actualCpm = (int) $params['actual_cpm_minor'];

        if ($actualCpm < (int) $placement->floor_cpm_minor) {
            throw new RuntimeException("Bid CPM ({$actualCpm}) is below placement floor CPM ({$placement->floor_cpm_minor})");
        }

        return MedAdCampaign::create([
            'id' => (string) Str::uuid(),
            'campaign_code' => 'CMP-'.Str::upper(Str::random(8)),
            'advertiser_entity_id' => $params['advertiser_entity_id'],
            'agency_entity_id' => $params['agency_entity_id'] ?? null,
            'agency_commission_pct' => (float) ($params['agency_commission_pct'] ?? 0.0),
            'channel_id' => $placement->channel_id,
            'placement_id' => $placement->id,
            'target_impressions' => (int) $params['target_impressions'],
            'verified_impressions' => 0,
            'floor_cpm_minor' => (int) $placement->floor_cpm_minor,
            'actual_cpm_minor' => $actualCpm,
            'total_spend_minor' => 0,
            'agency_commission_minor' => 0,
            'publisher_share_minor' => 0,
            'platform_share_minor' => 0,
            'status' => 'SCHEDULED',
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /**
     * Ingest append-only ad impression event with deduplication and velocity fraud checks.
     *
     * @param array{
     *     campaign_id: string,
     *     impression_hash: string,
     *     device_ip_hash: string,
     *     timestamp_utc: string
     * } $params
     */
    public function ingestImpression(array $params): MedAdImpression
    {
        $hash = $params['impression_hash'];
        if (MedAdImpression::where('impression_hash', $hash)->exists()) {
            throw new InvalidArgumentException("Duplicate impression hash: {$hash}");
        }

        // Fraud velocity check: same device_ip_hash within same second
        $recentCount = MedAdImpression::where('device_ip_hash', $params['device_ip_hash'])
            ->where('timestamp_utc', $params['timestamp_utc'])
            ->count();

        $isFraud = $recentCount >= 10;
        $isVerified = ! $isFraud;

        return MedAdImpression::create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $params['campaign_id'],
            'impression_hash' => $hash,
            'device_ip_hash' => $params['device_ip_hash'],
            'timestamp_utc' => $params['timestamp_utc'],
            'is_verified' => $isVerified,
            'is_fraud' => $isFraud,
            'fraud_reason' => $isFraud ? 'VELOCITY_LIMIT_EXCEEDED' : null,
        ]);
    }

    /**
     * Settle campaign spend based on verified impressions and multi-party revenue shares.
     */
    public function settleCampaign(string $campaignId): MedAdCampaign
    {
        $campaign = MedAdCampaign::findOrFail($campaignId);

        return DB::transaction(function () use ($campaign): MedAdCampaign {
            $verifiedCount = MedAdImpression::where('campaign_id', $campaign->id)
                ->where('is_verified', true)
                ->where('is_fraud', false)
                ->count();

            // CPM = Cost per 1,000 impressions
            $totalSpend = (int) intdiv($verifiedCount * $campaign->actual_cpm_minor, 1000);

            $channel = MedDistributionChannel::findOrFail($campaign->channel_id);
            $agencyPct = (float) $campaign->agency_commission_pct;
            $revSharePct = (float) $channel->revenue_share_pct;

            $agencyCommission = (int) round($totalSpend * ($agencyPct / 100.0));
            $netSpend = $totalSpend - $agencyCommission;
            $publisherShare = (int) round($netSpend * ($revSharePct / 100.0));
            $platformShare = $totalSpend - $agencyCommission - $publisherShare;

            $campaign->update([
                'verified_impressions' => $verifiedCount,
                'total_spend_minor' => $totalSpend,
                'agency_commission_minor' => $agencyCommission,
                'publisher_share_minor' => $publisherShare,
                'platform_share_minor' => $platformShare,
                'status' => 'COMPLETED',
            ]);

            if ($totalSpend > 0) {
                $lines = Posting::lines()->debit('med:ad_receivable:IDR', $totalSpend);

                if ($agencyCommission > 0) {
                    $lines->credit('med:agency_commission_payable:IDR', $agencyCommission);
                }
                if ($publisherShare > 0) {
                    $lines->credit('med:ad_publisher_payable:IDR', $publisherShare);
                }
                if ($platformShare > 0) {
                    $lines->credit('med:ad_platform_revenue:IDR', $platformShare);
                }

                $this->ledgerService->post(new PostingDTO(
                    type: 'MEDIA_AD_CAMPAIGN_SETTLEMENT',
                    description: "Ad campaign settlement {$campaign->campaign_code}",
                    idempotencyKey: "med:campaign:{$campaign->id}",
                    entries: $lines->toEntries('IDR'),
                    referenceType: 'MED_AD_CAMPAIGN',
                    referenceId: $campaign->campaign_code,
                ));
            }

            return $campaign;
        });
    }
}
