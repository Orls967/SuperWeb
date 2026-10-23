<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Mobile;

use Modules\Core\Domain\Contracts\EpodVerifierInterface;
use Modules\Core\Domain\Models\CoreMobileEpodRecord;

class EpodIntegrityVerifierService implements EpodVerifierInterface
{
    /**
     * Recalculates cryptographic SHA-256 hash and verifies against stored signature hash.
     */
    public function verifySignature(CoreMobileEpodRecord $record): bool
    {
        $deliveredAtString = $record->delivered_offline_at?->format('Y-m-d H:i:s') ?? '';

        $material = sprintf(
            '%s|%s|%s|%s',
            $record->shipment_ref,
            $record->recipient_name,
            $record->signature_payload_b64,
            $deliveredAtString
        );

        $computedHash = hash('sha256', $material);

        return hash_equals($computedHash, $record->signature_hash);
    }

    /**
     * Mark record as tampered if signature fails recalculation or manual audit flags discrepancy.
     */
    public function flagTampering(string $epodCode): bool
    {
        $record = CoreMobileEpodRecord::where('epod_code', $epodCode)->first();
        if (! $record) {
            return false;
        }

        $record->update(['is_tampered' => true]);

        return true;
    }
}
