<?php

declare(strict_types=1);

namespace Modules\Party\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Party\Domain\Enums\KybStatus;
use Modules\Party\Domain\Enums\KycDocumentStatus;
use Modules\Party\Domain\Models\KycDocument;
use Modules\Party\Exceptions\InvalidKycTransitionException;
use Modules\Shared\Application\BaseAction;

class RejectKycDocumentAction extends BaseAction
{
    public function execute(KycDocument $doc, string $reason): KycDocument
    {
        return DB::transaction(function () use ($doc, $reason) {
            if ($doc->status !== KycDocumentStatus::Pending) {
                throw new InvalidKycTransitionException("Document [{$doc->id}] is not in pending status.");
            }

            $doc->update([
                'status' => KycDocumentStatus::Rejected->value,
                'rejection_reason' => $reason,
            ]);

            $doc->party->update(['kyb_status' => KybStatus::Rejected->value]);

            $this->audit(
                action: 'kyc_document_rejected',
                auditable: $doc,
                newValues: ['reason' => $reason],
                impactType: 'compliance',
            );

            return $doc->fresh();
        });
    }
}
