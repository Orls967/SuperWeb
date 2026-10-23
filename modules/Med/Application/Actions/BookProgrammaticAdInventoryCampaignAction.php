<?php

declare(strict_types=1);

namespace Modules\Med\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Med\Domain\Models\MedAdCampaign;
use Modules\Med\Domain\Models\MedDistributionChannel;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class BookProgrammaticAdInventoryCampaignAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): MedAdCampaign
    {
        $channel = MedDistributionChannel::findOrFail($params['channel_id']);

        if ($channel->status !== 'ACTIVE') {
            throw new RuntimeException("Distribution channel [{$channel->channel_code}] is not active.");
        }

        return DB::transaction(function () use ($params, $channel) {
            $advertiser = (string) $params['advertiser_entity_id'];
            $impressions = (int) $params['target_impressions'];
            $floorCpm = (int) ($params['floor_cpm_minor'] ?? 50000);
            $actualCpm = max($floorCpm, (int) ($params['actual_cpm_minor'] ?? $floorCpm));

            $totalSpend = (int) round(($impressions / 1000) * $actualCpm);
            $publisherShare = (int) round(($totalSpend * $channel->revenue_share_pct) / 100);
            $platformShare = $totalSpend - $publisherShare;

            $campaignCode = 'AD-'.strtoupper(substr(hash('sha256', "{$advertiser}:{$channel->id}:{$impressions}:".microtime()), 0, 10));

            $campaign = MedAdCampaign::create([
                'id' => (string) Str::uuid(),
                'campaign_code' => $campaignCode,
                'advertiser_entity_id' => $advertiser,
                'channel_id' => $channel->id,
                'target_impressions' => $impressions,
                'verified_impressions' => 0,
                'floor_cpm_minor' => $floorCpm,
                'actual_cpm_minor' => $actualCpm,
                'total_spend_minor' => $totalSpend,
                'publisher_share_minor' => $publisherShare,
                'platform_share_minor' => $platformShare,
                'status' => 'SCHEDULED',
            ]);

            if ($totalSpend > 0) {
                $idempotencyKey = hash('sha256', "AD-CAMPAIGN:{$campaignCode}:{$totalSpend}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'MEDIA_AD_CAMPAIGN_SETTLEMENT',
                    description: "Ad campaign programmatic booking for {$campaignCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('med:ad_receivable:IDR', $totalSpend)
                        ->credit('med:ad_platform_revenue:IDR', $platformShare)
                        ->credit('med:ad_publisher_payable:IDR', $publisherShare)
                        ->toEntries('IDR'),
                    referenceType: 'MED_AD_CAMPAIGN',
                    referenceId: $campaignCode,
                ));
            }

            $this->audit(
                action: 'med_ad_campaign_booked',
                auditable: $campaign,
                newValues: ['campaign_code' => $campaignCode, 'total_spend' => $totalSpend],
                impactType: 'advertising',
            );

            return $campaign;
        });
    }
}
