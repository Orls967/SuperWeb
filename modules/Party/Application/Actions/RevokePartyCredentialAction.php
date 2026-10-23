<?php

declare(strict_types=1);

namespace Modules\Party\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Party\Domain\Enums\KybStatus;
use Modules\Party\Domain\Enums\KycDocumentStatus;
use Modules\Party\Domain\Models\KycDocument;
use Modules\Party\Domain\Models\Party;
use Modules\Shared\Application\BaseAction;

class RevokePartyCredentialAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): KycDocument
    {
        return DB::transaction(function () use ($params) {
            $party = Party::findOrFail($params['party_id']);
            $docId = $params['document_id'] ?? $params['kyc_document_id'];

            $document = KycDocument::where('party_id', $party->id)->findOrFail($docId);
            $reason = (string) ($params['reason'] ?? 'Compliance credential revocation');

            $document->update([
                'status' => KycDocumentStatus::Rejected,
                'rejection_reason' => $reason,
            ]);

            // If no approved documents left, downgrade KYB status to InReview or Rejected
            $hasApproved = KycDocument::where('party_id', $party->id)
                ->where('status', KycDocumentStatus::Approved)
                ->exists();

            if (! $hasApproved) {
                $party->update(['kyb_status' => KybStatus::InReview]);
            }

            $this->audit(
                action: 'party_credential_revoked',
                auditable: $document,
                newValues: ['document_id' => $document->id, 'party_id' => $party->id, 'reason' => $reason],
                impactType: 'kyc_revocation',
            );

            return $document;
        });
    }
}
