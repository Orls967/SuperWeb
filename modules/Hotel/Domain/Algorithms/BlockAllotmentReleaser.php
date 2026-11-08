<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use InvalidArgumentException;

/**
 * H14: BlockAllotmentReleaser
 * Pelepasan kuota kamar blok bertingkat (H-30, H-14, H-7) kembali ke inventori publik
 * dengan proteksi anti-oversell dan pembuktian matematis kuota sisa >= confirmed pickup.
 */
final class BlockAllotmentReleaser
{
    public const TIER_PROTECTED = 'PROTECTED';

    public const TIER_H30 = 'H30';

    public const TIER_H14 = 'H14';

    public const TIER_H7_FINAL = 'H7_FINAL';

    public function release(
        string $propertyId,
        string $blockId,
        int $initialRooms,
        int $currentAllocatedRooms,
        int $confirmedPickupRooms,
        int $daysUntilEvent,
        ?float $customReleaseRatio = null,
        string $secretKey = 'superweb-hotel-mice-salt'
    ): BlockAllotmentReleaseCertificate {
        if ($initialRooms <= 0) {
            throw new InvalidArgumentException('Initial rooms must be greater than zero.');
        }

        if ($currentAllocatedRooms < 0 || $currentAllocatedRooms > $initialRooms) {
            throw new InvalidArgumentException('Current allocated rooms is invalid.');
        }

        if ($confirmedPickupRooms < 0 || $confirmedPickupRooms > $currentAllocatedRooms) {
            throw new InvalidArgumentException('Confirmed pickup rooms cannot exceed allocated rooms.');
        }

        $unpicked = $currentAllocatedRooms - $confirmedPickupRooms;

        if ($daysUntilEvent > 30) {
            $cutoffTier = self::TIER_PROTECTED;
            $ratio = 0.0;
        } elseif ($daysUntilEvent > 14) {
            $cutoffTier = self::TIER_H30;
            $ratio = $customReleaseRatio ?? 0.25;
        } elseif ($daysUntilEvent > 7) {
            $cutoffTier = self::TIER_H14;
            $ratio = $customReleaseRatio ?? 0.50;
        } else {
            $cutoffTier = self::TIER_H7_FINAL;
            $ratio = 1.00;
        }

        $roomsToRelease = (int) floor($unpicked * $ratio);
        // Pastikan tidak melepaskan lebih dari unpicked
        $roomsToRelease = max(0, min($unpicked, $roomsToRelease));
        $remainingContracted = $currentAllocatedRooms - $roomsToRelease;

        $payload = implode('|', [
            $propertyId,
            $blockId,
            $daysUntilEvent,
            $cutoffTier,
            $initialRooms,
            $currentAllocatedRooms,
            $confirmedPickupRooms,
            $unpicked,
            $roomsToRelease,
            $remainingContracted,
        ]);

        $signatureHash = hash_hmac('sha256', $payload, $secretKey);

        return new BlockAllotmentReleaseCertificate(
            propertyId: $propertyId,
            blockId: $blockId,
            daysUntilEvent: $daysUntilEvent,
            cutoffTier: $cutoffTier,
            initialRooms: $initialRooms,
            currentAllocatedRooms: $currentAllocatedRooms,
            confirmedPickupRooms: $confirmedPickupRooms,
            unpickedBefore: $unpicked,
            roomsReleasedToPublic: $roomsToRelease,
            remainingContractedRooms: $remainingContracted,
            signatureHash: $signatureHash
        );
    }
}
