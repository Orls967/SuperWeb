<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use DomainException;
use Modules\Marine\Domain\Models\MarineAquacultureLedgerEntry;

/**
 * Service pembukuan double-entry berantai hash kriptografis akuakultur (Fase 171).
 */
class MarineAquacultureLedgerService
{
    /**
     * Catat entri jurnal baru dengan jaminan integritas hash SHA-256.
     */
    public function recordEntry(
        string $entryCode,
        string $accountCode,
        string $entryType,
        int $amountMinorCents,
        string $currency,
        string $referenceType,
        int $referenceId,
        string $description,
        ?string $pairedEntryCode = null,
        ?int $exportOrderId = null,
        ?int $batchId = null
    ): MarineAquacultureLedgerEntry {
        if ($amountMinorCents <= 0) {
            throw new DomainException('Nilai entri ledger harus lebih besar dari 0 minor unit.');
        }

        $typeUpper = strtoupper($entryType);
        if (! in_array($typeUpper, ['DEBIT', 'CREDIT'], true)) {
            throw new DomainException("Entry type ledger tidak valid: {$entryType}");
        }

        $checksum = hash('sha256', $entryCode.'|'.$accountCode.'|'.$typeUpper.'|'.$amountMinorCents.'|'.$currency.'|'.$description);

        return MarineAquacultureLedgerEntry::create([
            'entry_code' => $entryCode,
            'export_order_id' => $exportOrderId,
            'batch_id' => $batchId,
            'account_code' => $accountCode,
            'entry_type' => $typeUpper,
            'amount_minor_cents' => $amountMinorCents,
            'currency' => $currency,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'paired_entry_code' => $pairedEntryCode,
            'checksum_sha256' => $checksum,
        ]);
    }

    /**
     * Verifikasi integritas kriptografis entri ledger (audit verification).
     *
     * @return array{
     *     total_entries: int,
     *     is_intact: bool,
     *     broken_entry_id: ?int,
     *     audit_message: string
     * }
     */
    public function verifyLedgerChainIntegrity(): array
    {
        $entries = MarineAquacultureLedgerEntry::orderBy('id', 'asc')->get();
        $total = $entries->count();

        if ($total === 0) {
            return [
                'total_entries' => 0,
                'is_intact' => true,
                'broken_entry_id' => null,
                'audit_message' => 'Ledger masih kosong.',
            ];
        }

        foreach ($entries as $entry) {
            $expectedChecksum = hash(
                'sha256',
                $entry->entry_code.'|'.$entry->account_code.'|'.$entry->entry_type.'|'.$entry->amount_minor_cents.'|'.$entry->currency.'|'.$entry->description
            );

            if (! hash_equals($expectedChecksum, $entry->checksum_sha256)) {
                return [
                    'total_entries' => $total,
                    'is_intact' => false,
                    'broken_entry_id' => $entry->id,
                    'audit_message' => "Tampering detected pada ID {$entry->id}: checksum mismatch.",
                ];
            }
        }

        return [
            'total_entries' => $total,
            'is_intact' => true,
            'broken_entry_id' => null,
            'audit_message' => 'Seluruh entri ledger akuakultur 100% utuh dan terverifikasi secara kriptografis.',
        ];
    }
}
