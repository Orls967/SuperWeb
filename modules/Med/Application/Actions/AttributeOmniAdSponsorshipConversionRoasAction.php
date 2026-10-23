<?php

declare(strict_types=1);

namespace Modules\Med\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class AttributeOmniAdSponsorshipConversionRoasAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Enforces ad yield floor pricing, reconciles verified impressions vs claimed, and settles multi-party agency splits.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $campaignCode = (string) ($params['campaign_code'] ?? 'AD-CMP-'.hash('sha256', (string) ($params['advertiser_id'] ?? 'default')));
            $advertiserId = (string) ($params['advertiser_id'] ?? 'ADV-TECH-CORP');

            $bidCpmMinor = (int) ($params['bid_cpm_minor'] ?? 50000);
            $floorPriceCpmMinor = (int) ($params['floor_price_cpm_minor'] ?? 30000);

            if ($bidCpmMinor < $floorPriceCpmMinor) {
                throw new DomainException("Ad yield floor pricing violation: Bid CPM {$bidCpmMinor} is below minimum floor price {$floorPriceCpmMinor}");
            }

            $impressionsClaimed = (int) ($params['impressions_claimed'] ?? 100000);
            $impressionsVerified = (int) ($params['impressions_verified'] ?? 90000);

            // Billed strictly on verified impressions (per thousand)
            $totalBilledMinor = (int) round(($impressionsVerified / 1000) * $bidCpmMinor);

            // Multi-party split: Agency commission (e.g. 15%), Publisher share (e.g. 70%), Platform net fee (15%)
            $agencyRatePct = (int) ($params['agency_commission_pct'] ?? 15);
            $publisherRatePct = (int) ($params['publisher_share_pct'] ?? 70);

            $agencyCommissionMinor = (int) round(($totalBilledMinor * $agencyRatePct) / 100);
            $publisherPayableMinor = (int) round(($totalBilledMinor * $publisherRatePct) / 100);
            $platformNetFeeMinor = $totalBilledMinor - $agencyCommissionMinor - $publisherPayableMinor;

            if ($totalBilledMinor > 0) {
                $idempotencyKey = hash('sha256', "MED:AD-SPLIT:{$campaignCode}:{$totalBilledMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'MEDIA_AD_CAMPAIGN_SETTLEMENT',
                    description: "Omni ad campaign settlement for {$campaignCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('med:ad_receivable:IDR', $totalBilledMinor)
                        ->credit('med:ad_platform_revenue:IDR', $platformNetFeeMinor)
                        ->credit('med:agency_commission_payable:IDR', $agencyCommissionMinor)
                        ->credit('med:ad_publisher_payable:IDR', $publisherPayableMinor)
                        ->toEntries('IDR'),
                    referenceType: 'MED_AD_CAMPAIGN',
                    referenceId: $campaignCode,
                ));
            }

            // ROAS / Conversion attribution calculation
            $attributedConversions = (int) ($params['attributed_conversions'] ?? 450);
            $attributedRevenueMinor = (int) ($params['attributed_revenue_minor'] ?? 25000000);
            $roasMultiple = $totalBilledMinor > 0 ? (int) round(($attributedRevenueMinor / $totalBilledMinor) * 10) : 0; // e.g. 55 -> 5.5x

            $result = [
                'campaign_code' => $campaignCode,
                'advertiser_id' => $advertiserId,
                'bid_cpm_minor' => $bidCpmMinor,
                'impressions_claimed' => $impressionsClaimed,
                'impressions_verified' => $impressionsVerified,
                'total_billed_minor' => $totalBilledMinor,
                'agency_commission_minor' => $agencyCommissionMinor,
                'publisher_payable_minor' => $publisherPayableMinor,
                'platform_net_fee_minor' => $platformNetFeeMinor,
                'attributed_conversions' => $attributedConversions,
                'roas_multiple_basis' => $roasMultiple,
            ];

            $this->audit(
                action: 'med.ads.campaign_attributed_and_settled',
                newValues: [
                    'campaign_code' => $campaignCode,
                    'verified_impressions' => $impressionsVerified,
                    'billed_minor' => $totalBilledMinor,
                    'roas' => $roasMultiple,
                ],
                correlationId: "med_ad_{$campaignCode}",
                impactType: 'ad_sponsorship_settlement'
            );

            $this->outbox(
                eventType: 'OmniAdCampaignAttributedAndSettled',
                payload: [
                    'campaign_code' => $campaignCode,
                    'total_billed_minor' => $totalBilledMinor,
                    'impressions_verified' => $impressionsVerified,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:AD:{$campaignCode}")
            );

            return $result;
        });
    }
}
