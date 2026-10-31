<?php

declare(strict_types=1);

namespace Modules\Party\Contracts;

interface InvestorKycLookupInterface
{
    /**
     * Get KYC verification status for a user or KYC document.
     *
     * @return array{is_verified: bool, kyc_document_id?: ?string, status?: string, expires_at?: ?string}
     */
    public function getKycVerificationStatus(int $userId): array;

    /**
     * Get status of a specific KYC document.
     *
     * @return array{id: string, status: string, expires_at: ?string, is_valid: bool}|null
     */
    public function getDocumentStatus(string $documentId): ?array;
}
