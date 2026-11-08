<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final class BlockAllotmentReleaseCertificateVerifier
{
    public static function verify(BlockAllotmentReleaseCertificate $certificate, string $secretKey = 'superweb-hotel-mice-salt'): bool
    {
        $payload = implode('|', [
            $certificate->propertyId,
            $certificate->blockId,
            $certificate->daysUntilEvent,
            $certificate->cutoffTier,
            $certificate->initialRooms,
            $certificate->currentAllocatedRooms,
            $certificate->confirmedPickupRooms,
            $certificate->unpickedBefore,
            $certificate->roomsReleasedToPublic,
            $certificate->remainingContractedRooms,
        ]);

        $expectedHash = hash_hmac('sha256', $payload, $secretKey);

        return hash_equals($expectedHash, $certificate->signatureHash);
    }
}
