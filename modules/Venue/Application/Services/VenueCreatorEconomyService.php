<?php

namespace Modules\Venue\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Venue\Domain\Models\ContentAsset;
use Modules\Venue\Domain\Models\Creator;
use Modules\Venue\Domain\Models\MerchSale;
use Modules\Venue\Domain\Models\RightsContract;
use RuntimeException;

class VenueCreatorEconomyService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 115.1 Register Creator and Upload Content Asset
     */
    public function registerCreator(string $stageName, string $genre): Creator
    {
        return Creator::create([
            'creator_code' => 'CRT-'.strtoupper(bin2hex(random_bytes(4))),
            'stage_name' => $stageName,
            'genre' => $genre,
            'status' => 'ACTIVE',
        ]);
    }

    public function registerAsset(Creator $creator, string $title, string $mediaType, string $contentHash): ContentAsset
    {
        return ContentAsset::create([
            'asset_code' => 'AST-'.strtoupper(bin2hex(random_bytes(4))),
            'creator_id' => $creator->id,
            'title' => $title,
            'media_type' => $mediaType,
            'content_hash' => $contentHash,
            'license_type' => 'EXCLUSIVE',
        ]);
    }

    /**
     * 115.2 Create Rights Contract
     */
    public function createRightsContract(Creator $creator, float $royaltyPercent = 70.0, int $holdDays = 14): RightsContract
    {
        return RightsContract::create([
            'contract_code' => 'RGT-'.strtoupper(bin2hex(random_bytes(4))),
            'creator_id' => $creator->id,
            'royalty_share_percent' => $royaltyPercent,
            'payout_hold_window_days' => $holdDays,
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * 115.2 & 115.6 (a) Accrue Stream Revenue and Hold in Escrow until Claim Window Passes
     */
    public function accrueStreamRevenue(RightsContract $contract, int $streamGrossRevenueIdr): int
    {
        $royaltyAmountIdr = (int) round(($streamGrossRevenueIdr * $contract->royalty_share_percent) / 100);

        return DB::transaction(function () use ($contract, $streamGrossRevenueIdr, $royaltyAmountIdr) {
            $contract->increment('accrued_royalties_idr', $royaltyAmountIdr);
            $contract->increment('held_royalties_idr', $royaltyAmountIdr);

            // Post revenue & hold liability
            $this->ledgerService->post(new PostingDTO(
                type: 'CREATOR_STREAM_ROYALTY_ACCRUAL',
                description: "Accrue creator streaming royalty for contract {$contract->contract_code}",
                idempotencyKey: "VEN-RGT-{$contract->contract_code}-".time().'-'.rand(100, 999),
                entries: [
                    PostingEntryDTO::forCode('ven:streaming_receivable:IDR', 'IDR', $streamGrossRevenueIdr),
                    PostingEntryDTO::forCode('ven:platform_streaming_revenue:IDR', 'IDR', -($streamGrossRevenueIdr - $royaltyAmountIdr)),
                    PostingEntryDTO::forCode('ven:creator_held_royalty_escrow:IDR', 'IDR', -$royaltyAmountIdr),
                ],
                referenceType: 'RIGHTS_CONTRACT',
                referenceId: (string) $contract->id,
            ));

            return $royaltyAmountIdr;
        });
    }

    /**
     * 115.2 & 115.6 (b) Release Held Royalty Payout after Window Closes
     */
    public function releaseRoyaltyPayout(RightsContract $contract, bool $windowPassed = true): int
    {
        if (! $windowPassed) {
            throw new RuntimeException("Payout hold active: Cannot release royalties before claim dispute window of {$contract->payout_hold_window_days} days expires");
        }

        $amountToPay = $contract->held_royalties_idr;
        if ($amountToPay <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($contract, $amountToPay) {
            $contract->update([
                'held_royalties_idr' => 0,
                'paid_royalties_idr' => $contract->paid_royalties_idr + $amountToPay,
            ]);

            // Release from escrow to creator payable
            $this->ledgerService->post(new PostingDTO(
                type: 'CREATOR_ROYALTY_PAYOUT_RELEASE',
                description: "Release held royalty payout to creator for contract {$contract->contract_code}",
                idempotencyKey: "VEN-REL-{$contract->contract_code}-".time(),
                entries: [
                    PostingEntryDTO::forCode('ven:creator_held_royalty_escrow:IDR', 'IDR', $amountToPay),
                    PostingEntryDTO::forCode('ven:creator_payable:IDR', 'IDR', -$amountToPay),
                ],
                referenceType: 'RIGHTS_CONTRACT',
                referenceId: (string) $contract->id,
            ));

            return $amountToPay;
        });
    }

    /**
     * 115.5 & 115.6 (d) Merchandise Sales Revenue Split (Artist + Platform = Total Price)
     */
    public function recordMerchSale(
        Creator $creator,
        string $itemName,
        int $totalSalePriceIdr,
        float $artistSplitPercent = 60.0
    ): MerchSale {
        $artistShareIdr = (int) round(($totalSalePriceIdr * $artistSplitPercent) / 100);
        $platformShareIdr = $totalSalePriceIdr - $artistShareIdr; // Invariant: artist + platform = total

        return DB::transaction(function () use ($creator, $itemName, $totalSalePriceIdr, $artistShareIdr, $platformShareIdr) {
            $sale = MerchSale::create([
                'sale_code' => 'MRC-'.strtoupper(bin2hex(random_bytes(4))),
                'creator_id' => $creator->id,
                'item_name' => $itemName,
                'total_sale_price_idr' => $totalSalePriceIdr,
                'artist_share_idr' => $artistShareIdr,
                'platform_share_idr' => $platformShareIdr,
                'status' => 'SETTLED',
            ]);

            $this->ledgerService->post(new PostingDTO(
                type: 'CREATOR_MERCH_SALE_SPLIT',
                description: "Merchandise sale split for {$itemName} by {$creator->stage_name}",
                idempotencyKey: "VEN-MRC-{$sale->sale_code}",
                entries: [
                    PostingEntryDTO::forCode('ven:merch_sales_receivable:IDR', 'IDR', $totalSalePriceIdr),
                    PostingEntryDTO::forCode('ven:creator_payable:IDR', 'IDR', -$artistShareIdr),
                    PostingEntryDTO::forCode('ven:platform_merch_revenue:IDR', 'IDR', -$platformShareIdr),
                ],
                referenceType: 'MERCH_SALE',
                referenceId: (string) $sale->id,
            ));

            return $sale;
        });
    }
}
