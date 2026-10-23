<?php

declare(strict_types=1);

namespace Modules\Med\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class SettleMultiPlatformContentSyndicationWaterfallAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Settles multi-platform content syndication revenue, checks territorial IP licensing overlap,
     * and computes talent backend waterfall distribution with last-row rounding absorption.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $syndicationCode = (string) ($params['syndication_code'] ?? 'MED-SYN-'.hash('sha256', (string) ($params['project_code'] ?? 'default')));
            $projectCode = (string) ($params['project_code'] ?? 'PRJ-FILM-01');

            // Territorial exclusivity check
            $licenseTerritory = (string) ($params['target_territory'] ?? 'ID');
            /** @var list<string> $existingTerritories */
            $existingTerritories = $params['existing_licensed_territories'] ?? [];

            if (in_array($licenseTerritory, $existingTerritories, true)) {
                throw new DomainException("Territorial IP licensing overlap rejected: Territory {$licenseTerritory} already licensed for project {$projectCode}");
            }

            $boxOfficeRevenueMinor = (int) ($params['box_office_revenue_minor'] ?? 0);
            $streamingRevenueMinor = (int) ($params['streaming_revenue_minor'] ?? 0);
            $totalAuditedRevenueMinor = $boxOfficeRevenueMinor + $streamingRevenueMinor;

            // Royalty pool: e.g. 20% of net revenue
            $royaltyPoolPct = (int) ($params['royalty_pool_pct'] ?? 20);
            $totalRoyaltyPoolMinor = (int) round(($totalAuditedRevenueMinor * $royaltyPoolPct) / 100);

            /** @var list<array{talent_name: string, role: string, share_pct: int}> $talentShares */
            $talentShares = $params['talent_shares'] ?? [];

            $distributedRoyaltyMinor = 0;
            $talentPayouts = [];
            $talentCount = count($talentShares);

            foreach ($talentShares as $index => $talent) {
                $isLast = ($index === $talentCount - 1);
                $sharePct = (int) $talent['share_pct'];

                if ($isLast) {
                    // Last row absorbs penny rounding variance
                    $payoutMinor = $totalRoyaltyPoolMinor - $distributedRoyaltyMinor;
                } else {
                    $payoutMinor = (int) round(($totalRoyaltyPoolMinor * $sharePct) / 100);
                    $distributedRoyaltyMinor += $payoutMinor;
                }

                $talentPayouts[] = [
                    'talent_name' => $talent['talent_name'],
                    'role' => $talent['role'],
                    'share_pct' => $sharePct,
                    'payout_minor' => $payoutMinor,
                ];
            }

            if ($totalRoyaltyPoolMinor > 0) {
                $idempotencyKey = hash('sha256', "MED:TALENT-WATERFALL:{$syndicationCode}:{$totalRoyaltyPoolMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'MEDIA_TALENT_ROYALTY_ACCRUAL',
                    description: "Talent royalty waterfall accrual for {$projectCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('med:talent_royalty_expense:IDR', $totalRoyaltyPoolMinor)
                        ->credit('med:talent_royalty_payable:IDR', $totalRoyaltyPoolMinor)
                        ->toEntries('IDR'),
                    referenceType: 'MED_WATERFALL_ROYALTY',
                    referenceId: $syndicationCode,
                ));
            }

            $result = [
                'syndication_code' => $syndicationCode,
                'project_code' => $projectCode,
                'target_territory' => $licenseTerritory,
                'box_office_revenue_minor' => $boxOfficeRevenueMinor,
                'streaming_revenue_minor' => $streamingRevenueMinor,
                'total_audited_revenue_minor' => $totalAuditedRevenueMinor,
                'total_royalty_pool_minor' => $totalRoyaltyPoolMinor,
                'talent_payouts' => $talentPayouts,
            ];

            $this->audit(
                action: 'med.waterfall.syndication_settled',
                newValues: [
                    'syndication_code' => $syndicationCode,
                    'project_code' => $projectCode,
                    'total_revenue_minor' => $totalAuditedRevenueMinor,
                    'royalty_pool_minor' => $totalRoyaltyPoolMinor,
                ],
                correlationId: "med_syn_{$syndicationCode}",
                impactType: 'content_syndication_settlement'
            );

            $this->outbox(
                eventType: 'ContentSyndicationWaterfallSettled',
                payload: [
                    'syndication_code' => $syndicationCode,
                    'project_code' => $projectCode,
                    'total_royalty_pool_minor' => $totalRoyaltyPoolMinor,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:MED:{$syndicationCode}")
            );

            return $result;
        });
    }
}
