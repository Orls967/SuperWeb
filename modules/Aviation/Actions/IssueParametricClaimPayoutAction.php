<?php

declare(strict_types=1);

namespace Modules\Aviation\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Models\AviationParametricClaim;

class IssueParametricClaimPayoutAction
{
    /**
     * Cairkan dana klaim asuransi parametrik yang telah disetujui ke rekening/dompet penumpang.
     *
     * @return array{claim_id: int, payout_idr: int, status: string, disbursement_reference: string}
     */
    public function execute(int $claimId): array
    {
        return DB::transaction(function () use ($claimId) {
            $claim = AviationParametricClaim::lockForUpdate()->findOrFail($claimId);

            if ($claim->claim_status === 'disbursed') {
                throw new DomainException(sprintf('Klaim [%d] sudah pernah dicairkan sebelumnya.', $claimId));
            }

            $claim->claim_status = 'disbursed';
            $disburseRef = sprintf('DISB-PARAM-%s-%d', date('Ymd'), $claim->id);
            $claim->external_reference = $disburseRef;
            $claim->save();

            $payoutIdr = (int) round($claim->payout_amount_minor_unit / 100);

            return [
                'claim_id' => $claim->id,
                'payout_idr' => $payoutIdr,
                'status' => 'disbursed',
                'disbursement_reference' => $disburseRef,
            ];
        });
    }
}
