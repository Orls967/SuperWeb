<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\ContentAsset;
use Modules\Venue\Domain\Models\Creator;
use Modules\Venue\Domain\Models\MerchSale;
use Modules\Venue\Domain\Models\RightsContract;

class SyndicateCreatorMediaRightsAndWaterfallRoyaltyAction extends BaseAction
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Syndicate creator media rights, accrue streaming waterfall royalty to escrow, and split merch sales.
     *
     * @return array{creator: Creator, asset: ContentAsset, contract: RightsContract, accrued_royalty_idr: int, merch_sale: ?MerchSale}
     */
    public function handle(
        int $creatorId,
        string $assetTitle,
        string $mediaType,
        string $contentHash,
        float $royaltyPercent = 70.0,
        int $streamGrossRevenueIdr = 10000000,
        int $merchSaleGrossIdr = 0,
        string $merchItemName = 'Tour T-Shirt 2026'
    ): array {
        return DB::transaction(function () use (
            $creatorId,
            $assetTitle,
            $mediaType,
            $contentHash,
            $royaltyPercent,
            $streamGrossRevenueIdr,
            $merchSaleGrossIdr,
            $merchItemName
        ) {
            $creator = Creator::lockForUpdate()->findOrFail($creatorId);

            // 115.1 Content Asset Registration
            $assetCount = ContentAsset::where('creator_id', $creator->id)->count() + 1;
            $assetCode = sprintf('AST-%s-%04d', $creator->creator_code, $assetCount);

            $asset = ContentAsset::create([
                'asset_code' => $assetCode,
                'creator_id' => $creator->id,
                'title' => $assetTitle,
                'media_type' => $mediaType,
                'content_hash' => $contentHash,
                'license_type' => 'EXCLUSIVE',
            ]);

            // 115.2 Rights Contract
            $contract = RightsContract::where('creator_id', $creator->id)->first();
            if (! $contract) {
                $contract = RightsContract::create([
                    'contract_code' => 'RGT-'.strtoupper(bin2hex(random_bytes(4))),
                    'creator_id' => $creator->id,
                    'royalty_share_percent' => $royaltyPercent,
                    'payout_hold_window_days' => 14,
                    'accrued_royalties_idr' => 0,
                    'held_royalties_idr' => 0,
                    'paid_royalties_idr' => 0,
                    'status' => 'ACTIVE',
                ]);
            }

            // 115.2 & 115.6 (a) Accrue stream waterfall revenue into held escrow
            $royaltyShareIdr = (int) round(($streamGrossRevenueIdr * $contract->royalty_share_percent) / 100);
            $platformShareIdr = $streamGrossRevenueIdr - $royaltyShareIdr;

            if ($streamGrossRevenueIdr > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'CREATOR_STREAM_ROYALTY_ACCRUAL',
                    description: "Accrue creator streaming royalty for contract {$contract->contract_code}",
                    idempotencyKey: "venue:stream_accrual:{$contract->contract_code}:{$streamGrossRevenueIdr}",
                    entries: Posting::lines()
                        ->debit('ven:streaming_receivable:IDR', $streamGrossRevenueIdr)
                        ->credit('ven:platform_streaming_revenue:IDR', $platformShareIdr)
                        ->credit('ven:creator_held_royalty_escrow:IDR', $royaltyShareIdr)
                        ->toEntries('IDR'),
                    referenceType: 'RIGHTS_CONTRACT',
                    referenceId: (string) $contract->id,
                ));

                $contract->increment('accrued_royalties_idr', $royaltyShareIdr);
                $contract->increment('held_royalties_idr', $royaltyShareIdr);
            }

            // 115.5 Merch Economy Revenue Split
            $merchSale = null;
            if ($merchSaleGrossIdr > 0) {
                $artistShareIdr = (int) round(($merchSaleGrossIdr * 60.0) / 100);
                $platformMerchShareIdr = $merchSaleGrossIdr - $artistShareIdr;

                $saleCount = MerchSale::count() + 1;
                $saleCode = sprintf('MRC-%06d', $saleCount);

                $merchSale = MerchSale::create([
                    'sale_code' => $saleCode,
                    'creator_id' => $creator->id,
                    'item_name' => $merchItemName,
                    'total_sale_price_idr' => $merchSaleGrossIdr,
                    'artist_share_idr' => $artistShareIdr,
                    'platform_share_idr' => $platformMerchShareIdr,
                    'status' => 'SETTLED',
                ]);

                $this->ledgerService->post(new PostingDTO(
                    type: 'CREATOR_MERCH_SALE_SPLIT',
                    description: "Merchandise sale split for {$merchItemName} by {$creator->stage_name}",
                    idempotencyKey: "VEN-MRC-{$saleCode}",
                    entries: Posting::lines()
                        ->debit('ven:merch_sales_receivable:IDR', $merchSaleGrossIdr)
                        ->credit('ven:creator_payable:IDR', $artistShareIdr)
                        ->credit('ven:platform_merch_revenue:IDR', $platformMerchShareIdr)
                        ->toEntries('IDR'),
                    referenceType: 'MERCH_SALE',
                    referenceId: (string) $merchSale->id,
                ));
            }

            $this->audit(
                action: 'venue.creator_rights.syndicated',
                auditable: $asset,
                oldValues: null,
                newValues: [
                    'creator_code' => $creator->creator_code,
                    'asset_code' => $assetCode,
                    'accrued_royalty_idr' => $royaltyShareIdr,
                    'merch_sale_code' => $merchSale?->sale_code,
                ],
                correlationId: "ven_rgt_{$contract->contract_code}",
                impactType: 'creator_rights_syndication'
            );

            return [
                'creator' => $creator,
                'asset' => $asset,
                'contract' => $contract,
                'accrued_royalty_idr' => $royaltyShareIdr,
                'merch_sale' => $merchSale,
            ];
        });
    }
}
