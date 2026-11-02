<?php

declare(strict_types=1);

namespace Modules\Party\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Party\Contracts\InvestorKycLookupInterface;

class PartyKycLookupService implements InvestorKycLookupInterface
{
    public function getKycVerificationStatus(int $userId): array
    {
        $doc = DB::table('pty_kyc_documents')
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now()->toDateString());
            })
            ->first();

        if ($doc) {
            return [
                'is_verified' => true,
                'kyc_document_id' => (string) $doc->id,
                'status' => (string) $doc->status,
                'expires_at' => $doc->expires_at ? (string) $doc->expires_at : null,
            ];
        }

        return [
            'is_verified' => false,
            'kyc_document_id' => null,
            'status' => 'unverified',
            'expires_at' => null,
        ];
    }

    public function getDocumentStatus(string $documentId): ?array
    {
        $doc = DB::table('pty_kyc_documents')
            ->where('id', $documentId)
            ->first();

        if (! $doc) {
            return null;
        }

        $isValid = ($doc->status === 'approved') &&
            (! $doc->expires_at || $doc->expires_at >= now()->toDateString());

        return [
            'id' => (string) $doc->id,
            'status' => (string) $doc->status,
            'expires_at' => $doc->expires_at ? (string) $doc->expires_at : null,
            'is_valid' => $isValid,
        ];
    }
}
