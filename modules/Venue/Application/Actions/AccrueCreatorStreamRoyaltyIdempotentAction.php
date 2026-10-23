<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\RightsContract;

class AccrueCreatorStreamRoyaltyIdempotentAction extends BaseAction
{
    /**
     * Accrue streaming rights royalty into creator held escrow balance idempotently.
     *
     * @return array{contract: RightsContract, gross_stream_revenue_idr: int, creator_share_idr: int, platform_fee_idr: int, current_held_idr: int}
     */
    public function handle(
        int $rightsContractId,
        int $streamCount,
        int $ratePerStreamIdr = 150
    ): array {
        return DB::transaction(function () use ($rightsContractId, $streamCount, $ratePerStreamIdr) {
            $contract = RightsContract::lockForUpdate()->findOrFail($rightsContractId);

            $grossRevenueIdr = $streamCount * $ratePerStreamIdr;
            $sharePct = (float) $contract->royalty_share_percent;

            $creatorShareIdr = (int) round(($grossRevenueIdr * $sharePct) / 100);
            $platformFeeIdr = $grossRevenueIdr - $creatorShareIdr;

            $contract->accrued_royalties_idr += $grossRevenueIdr;
            $contract->held_royalties_idr += $creatorShareIdr;
            $contract->save();

            $idempotencyKey = hash('sha256', "stream_royalty_{$contract->id}_{$streamCount}_".date('YmdH'));

            $this->audit(
                action: 'venue.creator.royalty_accrued',
                auditable: $contract,
                oldValues: ['held_idr' => $contract->held_royalties_idr - $creatorShareIdr],
                newValues: [
                    'contract_code' => $contract->contract_code,
                    'stream_count' => $streamCount,
                    'gross_revenue_idr' => $grossRevenueIdr,
                    'creator_share_idr' => $creatorShareIdr,
                    'current_held_idr' => $contract->held_royalties_idr,
                ],
                correlationId: "ven_roy_{$contract->contract_code}",
                impactType: 'creator_streaming_royalty'
            );

            $this->outbox(
                eventType: 'CreatorStreamRoyaltyAccrued',
                payload: [
                    'contract_id' => $contract->id,
                    'creator_id' => $contract->creator_id,
                    'creator_share_idr' => $creatorShareIdr,
                    'platform_fee_idr' => $platformFeeIdr,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'contract' => $contract,
                'gross_stream_revenue_idr' => $grossRevenueIdr,
                'creator_share_idr' => $creatorShareIdr,
                'platform_fee_idr' => $platformFeeIdr,
                'current_held_idr' => (int) $contract->held_royalties_idr,
            ];
        });
    }
}
