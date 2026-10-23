<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\ArtistContract;

class ExecuteArtistContractEscrowAndWhtPayoutAction extends BaseAction
{
    /**
     * Settle artist contract revenue escrow with Indonesian Withholding Tax (PPh 23 2% or PPh 21 5%).
     *
     * @return array{contract: ArtistContract, gross_share_idr: int, wht_tax_idr: int, advance_deducted_idr: int, net_payout_idr: int}
     */
    public function handle(
        string $artistContractId,
        int $totalDoorSalesIdr,
        float $whtRatePct = 2.0
    ): array {
        return DB::transaction(function () use ($artistContractId, $totalDoorSalesIdr, $whtRatePct) {
            $contract = ArtistContract::lockForUpdate()->findOrFail($artistContractId);

            $doorSharePct = (float) $contract->door_share_percentage;
            $grossShareIdr = (int) round(($totalDoorSalesIdr * $doorSharePct) / 100);

            // Indonesian Withholding Tax (PPh 23: 2%)
            $whtTaxIdr = (int) round(($grossShareIdr * $whtRatePct) / 100);

            $advanceDeductedIdr = (int) $contract->advance_amount;
            $netPayoutIdr = max(0, $grossShareIdr - $whtTaxIdr - $advanceDeductedIdr);

            $contract->total_door_sales = $totalDoorSalesIdr;
            $contract->door_share_gross = $grossShareIdr;
            $contract->net_payout_amount = $netPayoutIdr;
            $contract->status = 'settled';
            $contract->save();

            $this->audit(
                action: 'venue.artist.settled',
                auditable: $contract,
                oldValues: ['status' => 'active'],
                newValues: [
                    'contract_number' => $contract->contract_number,
                    'total_door_sales' => $totalDoorSalesIdr,
                    'gross_share_idr' => $grossShareIdr,
                    'wht_tax_idr' => $whtTaxIdr,
                    'net_payout_idr' => $netPayoutIdr,
                ],
                correlationId: "ven_art_{$contract->contract_number}",
                impactType: 'artist_escrow_settlement'
            );

            $this->outbox(
                eventType: 'ArtistContractSettled',
                payload: [
                    'contract_id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'gross_share_idr' => $grossShareIdr,
                    'wht_tax_idr' => $whtTaxIdr,
                    'net_payout_idr' => $netPayoutIdr,
                ],
                idempotencyKey: hash('sha256', "art_settle_{$contract->contract_number}")
            );

            return [
                'contract' => $contract,
                'gross_share_idr' => $grossShareIdr,
                'wht_tax_idr' => $whtTaxIdr,
                'advance_deducted_idr' => $advanceDeductedIdr,
                'net_payout_idr' => $netPayoutIdr,
            ];
        });
    }
}
