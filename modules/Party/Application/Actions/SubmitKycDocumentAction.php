<?php

declare(strict_types=1);

namespace Modules\Party\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Party\Application\Services\SanctionScreeningService;
use Modules\Party\Domain\Enums\KybStatus;
use Modules\Party\Domain\Enums\KycDocumentStatus;
use Modules\Party\Domain\Models\KycDocument;
use Modules\Party\Domain\Models\Party;
use Modules\Shared\Application\BaseAction;

class SubmitKycDocumentAction extends BaseAction
{
    public function __construct(
        private readonly SanctionScreeningService $screening
    ) {}

    /**
     * Submit a KYC document and trigger sanctions screening on first submission.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(Party $party, array $data): KycDocument
    {
        return DB::transaction(function () use ($party, $data) {
            $doc = KycDocument::create([
                'party_id' => $party->id,
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'] ?? null,
                'document_number_hash' => isset($data['document_number'])
                    ? hash('sha256', strtoupper($data['document_number']))
                    : null,
                'issuer' => $data['issuer'] ?? null,
                'issued_at' => $data['issued_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'status' => KycDocumentStatus::Pending->value,
                'file_path' => $data['file_path'] ?? null,
                'file_checksum' => $data['file_checksum'] ?? null,
            ]);

            // Update KYB status to in_review if not already past that
            if ($party->kyb_status === KybStatus::Pending) {
                $party->update(['kyb_status' => KybStatus::InReview->value]);
            }

            $this->audit(
                action: 'kyc_document_submitted',
                auditable: $party,
                newValues: ['document_type' => $data['document_type'], 'document_id' => $doc->id],
                impactType: 'compliance',
            );

            return $doc;
        });
    }
}
