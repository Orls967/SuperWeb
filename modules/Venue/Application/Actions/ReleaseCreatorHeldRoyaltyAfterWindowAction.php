<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\RightsContract;

class ReleaseCreatorHeldRoyaltyAfterWindowAction extends BaseAction
{
    /**
     * Release held royalties to creator payout after dispute resolution window expires.
     *
     * @return array{contract: RightsContract, released_amount_idr: int, wht_tax_idr: int, net_payout_idr: int}
     */
    public function handle(
        int $rightsContractId,
        float $whtRatePct = 2.5
    ): array {
        return DB::transaction(function () use ($rightsContractId, $whtRatePct) {
            $contract = RightsContract::lockForUpdate()->findOrFail($rightsContractId);

            $heldAmountIdr = (int) $contract->held_royalties_idr;

            if ($heldAmountIdr <= 0) {
                throw new \RuntimeException('No held royalties available for release.');
            }

            // Tax deduction (Indonesian PPh 21/23 creator royalty withholding tax: 2.5%)
            $whtTaxIdr = (int) round(($heldAmountIdr * $whtRatePct) / 100);
            $netPayoutIdr = $heldAmountIdr - $whtTaxIdr;

            $contract->held_royalties_idr = 0;
            $contract->paid_royalties_idr += $heldAmountIdr;
            $contract->save();

            $this->audit(
                action: 'venue.creator.royalty_released',
                auditable: $contract,
                oldValues: ['held_idr' => $heldAmountIdr],
                newValues: [
                    'contract_code' => $contract->contract_code,
                    'released_amount_idr' => $heldAmountIdr,
                    'wht_tax_idr' => $whtTaxIdr,
                    'net_payout_idr' => $netPayoutIdr,
                    'total_paid_idr' => $contract->paid_royalties_idr,
                ],
                correlationId: "ven_rel_{$contract->contract_code}_".date('YmdHis'),
                impactType: 'creator_royalty_payout'
            );

            $this->outbox(
                eventType: 'CreatorRoyaltyReleased',
                payload: [
                    'contract_id' => $contract->id,
                    'creator_id' => $contract->creator_id,
                    'released_amount_idr' => $heldAmountIdr,
                    'wht_tax_idr' => $whtTaxIdr,
                    'net_payout_idr' => $netPayoutIdr,
                ],
                idempotencyKey: hash('sha256', "release_roy_{$contract->id}_".date('Ymd'))
            );

            return [
                'contract' => $contract,
                'released_amount_idr' => $heldAmountIdr,
                'wht_tax_idr' => $whtTaxIdr,
                'net_payout_idr' => $netPayoutIdr,
            ];
        });
    }
}
