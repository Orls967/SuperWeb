<?php

declare(strict_types=1);

namespace Modules\Party\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Party\Domain\Enums\KybStatus;
use Modules\Party\Domain\Enums\KycDocumentStatus;
use Modules\Party\Domain\Enums\PartyStatus;
use Modules\Party\Domain\Models\KycDocument;
use Modules\Party\Exceptions\InvalidKycTransitionException;
use Modules\Shared\Application\BaseAction;

class ApproveKycDocumentAction extends BaseAction
{
    public function execute(KycDocument $doc, ?string $approvedBy = null): KycDocument
    {
        return DB::transaction(function () use ($doc, $approvedBy) {
            if ($doc->status !== KycDocumentStatus::Pending) {
                throw new InvalidKycTransitionException("Document [{$doc->id}] is not in pending status.");
            }

            $doc->update(['status' => KycDocumentStatus::Approved->value]);
            $party = $doc->party;

            // If all required docs approved → transition KYB to verified
            $pendingCount = $party->kycDocuments()
                ->where('status', KycDocumentStatus::Pending->value)
                ->count();

            if ($pendingCount === 0) {
                $party->update(['kyb_status' => KybStatus::Verified->value]);

                // Auto-transition party status to verified if sanctions clear
                $latestCheck = $party->latestSanctionCheck();
                if ($latestCheck && $latestCheck->isClear() && $party->status === PartyStatus::Pending) {
                    $party->update(['status' => PartyStatus::Verified->value]);
                }
            }

            $this->audit(
                action: 'kyc_document_approved',
                auditable: $doc,
                newValues: ['approved_by' => $approvedBy],
                impactType: 'compliance',
            );

            return $doc->fresh();
        });
    }
}
