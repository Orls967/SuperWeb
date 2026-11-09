<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Billing;

use DomainException;
use Modules\Hospital\Domain\Models\ClaimDispute;

final class ResolveClaimDisputeAction
{
    /**
     * @param  array{dispute_id: int, reconciled_amount_idr: int, verdict: string, status: string}  $data
     */
    public function execute(array $data): ClaimDispute
    {
        $disputeId = (int) $data['dispute_id'];
        $dispute = ClaimDispute::find($disputeId);

        if (! $dispute) {
            throw new DomainException("Dispute klaim #{$disputeId} tidak ditemukan.");
        }

        $reconciled = max(0, (int) $data['reconciled_amount_idr']);
        $status = strtoupper($data['status']);

        $dispute->reconciled_amount_idr = $reconciled;
        $dispute->payer_verdict = $data['verdict'];
        $dispute->status = $status;
        $dispute->resolved_at = now();
        $dispute->save();

        return $dispute;
    }
}
