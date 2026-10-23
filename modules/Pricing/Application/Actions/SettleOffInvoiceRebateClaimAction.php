<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Actions;

use InvalidArgumentException;
use Modules\Pricing\Domain\Models\PromotionClaim;

class SettleOffInvoiceRebateClaimAction
{
    /**
     * Settle off-invoice promo or volume rebate claim submitted by distributor.
     *
     * @return array{claim: PromotionClaim, settled_amount_idr: int, status: string}
     */
    public function execute(
        PromotionClaim $claim,
        int $approvedAmountIdr,
        ?string $settlementNotes = null,
        ?int $approvalId = null
    ): array {
        if ($claim->status === 'settled' || $claim->status === 'rejected') {
            throw new InvalidArgumentException(
                sprintf('Klaim rebate %s sudah dalam status %s dan tidak dapat diselesaikan ulang.', (string) $claim->id, $claim->status)
            );
        }

        if ($approvedAmountIdr <= 0) {
            throw new InvalidArgumentException('Nilai penyelesaian klaim rebate harus lebih besar dari 0.');
        }

        $claim->update([
            'amount_idr' => $approvedAmountIdr,
            'status' => 'settled',
            'evidence_note' => $settlementNotes ?? $claim->evidence_note,
            'approval_id' => $approvalId,
        ]);

        return [
            'claim' => $claim->fresh(),
            'settled_amount_idr' => $approvedAmountIdr,
            'status' => 'settled',
        ];
    }
}
