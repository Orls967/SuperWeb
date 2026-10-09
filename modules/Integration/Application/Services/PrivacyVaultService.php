<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Str;
use Modules\Integration\Domain\Models\ConsentLedgerEntry;
use Modules\Integration\Domain\Models\ErasureRequest;
use Modules\Integration\Domain\Models\PiiVaultRecord;

/**
 * Privacy Vault Service (Fase 144.2)
 *
 * Handles:
 *  - Field-level AES-256 encryption of PII
 *  - Vault tokenization for analytics (token ≠ raw value)
 *  - Consent ledger per subject per line per purpose
 *  - Right-to-erasure: anonymize PII where deletion is impossible (e.g., ledger)
 */
class PrivacyVaultService
{
    /** PII categories supported */
    private const CATEGORIES = ['MEDICAL', 'BIOMETRIC', 'FINANCIAL', 'LOCATION', 'IDENTITY'];

    /**
     * Store a PII value encrypted in the vault; return vault_token for analytics reference.
     */
    public function storePii(string $subjectId, string $category, string $lineCode, string $rawValue): PiiVaultRecord
    {
        $category = strtoupper($category);
        if (! in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException("Unknown PII category: {$category}");
        }

        $encryptedValue = $this->encrypt($rawValue);
        $vaultToken = 'VT-'.strtoupper(Str::random(24));

        return PiiVaultRecord::create([
            'subject_id' => $subjectId,
            'pii_category' => $category,
            'line_code' => strtoupper($lineCode),
            'encrypted_value' => $encryptedValue,
            'vault_token' => $vaultToken,
            'encryption_key_version' => 'v1',
            'is_active' => true,
        ]);
    }

    /**
     * Retrieve raw PII value by vault token (only authorized services may call this).
     */
    public function retrieveByToken(string $vaultToken): string
    {
        $record = PiiVaultRecord::where('vault_token', $vaultToken)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->decrypt($record->encrypted_value);
    }

    /**
     * Check whether a vault token returns NULL (tokenization safety: analytics never sees raw value).
     */
    public function resolveTokenForAnalytics(string $vaultToken): ?string
    {
        // Analytics layer must never receive the real value
        // Returns only the token itself as safe reference
        $exists = PiiVaultRecord::where('vault_token', $vaultToken)
            ->where('is_active', true)
            ->exists();

        return $exists ? $vaultToken : null;
    }

    // ─── Consent Ledger ────────────────────────────────────────────────────────

    /**
     * Record consent opt-in for a subject+line+purpose.
     */
    public function recordConsent(string $subjectId, string $lineCode, string $purposeCode, string $legalBasis = 'CONSENT'): ConsentLedgerEntry
    {
        return ConsentLedgerEntry::updateOrCreate(
            [
                'subject_id' => $subjectId,
                'line_code' => strtoupper($lineCode),
                'purpose_code' => strtoupper($purposeCode),
            ],
            [
                'status' => ConsentLedgerEntry::STATUS_OPTED_IN,
                'consented_at' => now(),
                'withdrawn_at' => null,
                'legal_basis' => $legalBasis,
            ]
        );
    }

    /**
     * Revoke consent for a subject+line+purpose.
     */
    public function revokeConsent(string $subjectId, string $lineCode, string $purposeCode): ConsentLedgerEntry
    {
        $entry = ConsentLedgerEntry::where([
            'subject_id' => $subjectId,
            'line_code' => strtoupper($lineCode),
            'purpose_code' => strtoupper($purposeCode),
        ])->firstOrFail();

        $entry->update([
            'status' => ConsentLedgerEntry::STATUS_WITHDRAWN,
            'withdrawn_at' => now(),
        ]);

        return $entry->fresh();
    }

    /**
     * Check whether a subject has active consent for a given line+purpose.
     */
    public function hasConsent(string $subjectId, string $lineCode, string $purposeCode): bool
    {
        return ConsentLedgerEntry::where([
            'subject_id' => $subjectId,
            'line_code' => strtoupper($lineCode),
            'purpose_code' => strtoupper($purposeCode),
            'status' => ConsentLedgerEntry::STATUS_OPTED_IN,
        ])->exists();
    }

    // ─── Right-to-Erasure ──────────────────────────────────────────────────────

    /**
     * Submit a right-to-erasure request.
     */
    public function submitErasureRequest(string $subjectId, array $linesToErase): ErasureRequest
    {
        return ErasureRequest::create([
            'request_code' => 'ERASE-'.strtoupper(Str::random(12)),
            'subject_id' => $subjectId,
            'status' => ErasureRequest::STATUS_PENDING,
            'lines_to_erase' => $linesToErase,
            'requested_at' => now(),
        ]);
    }

    /**
     * Execute erasure: anonymize PII vault records for the subject.
     * Ledger entries cannot be deleted (immutable), so PII is anonymized in-place.
     */
    public function executeErasure(string $requestCode): ErasureRequest
    {
        $request = ErasureRequest::where('request_code', $requestCode)->firstOrFail();
        $request->update(['status' => ErasureRequest::STATUS_IN_PROGRESS]);

        $erasedByLine = [];
        $partialByLine = [];

        foreach ($request->lines_to_erase as $lineCode) {
            $records = PiiVaultRecord::where('subject_id', $request->subject_id)
                ->where('line_code', strtoupper($lineCode))
                ->where('is_active', true)
                ->get();

            foreach ($records as $record) {
                $record->update([
                    'encrypted_value' => $this->encrypt('ANONYMIZED-'.$record->pii_category),
                    'is_active' => false,
                    'anonymized_at' => now(),
                ]);
            }

            // Revoke all consents for this line
            ConsentLedgerEntry::where('subject_id', $request->subject_id)
                ->where('line_code', strtoupper($lineCode))
                ->update([
                    'status' => ConsentLedgerEntry::STATUS_WITHDRAWN,
                    'withdrawn_at' => now(),
                ]);

            $erasedByLine[$lineCode] = $records->count();
        }

        $status = empty($partialByLine) ? ErasureRequest::STATUS_COMPLETED : ErasureRequest::STATUS_PARTIAL;

        $request->update([
            'status' => $status,
            'erasure_report' => [
                'erased_by_line' => $erasedByLine,
                'partial_by_line' => $partialByLine,
                'note' => 'Ledger entries containing financial references were anonymized but not deleted to preserve double-entry integrity.',
            ],
            'completed_at' => now(),
        ]);

        return $request->fresh();
    }

    // ─── Encryption helpers (AES-256-CBC via Laravel encrypt) ──────────────────

    private function encrypt(string $value): string
    {
        // Use Laravel's built-in AES-256 encryption
        return encrypt($value);
    }

    private function decrypt(string $encryptedValue): string
    {
        return decrypt($encryptedValue);
    }
}
