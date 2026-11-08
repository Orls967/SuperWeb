<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final readonly class BlockAllotmentReleaseCertificate
{
    public function __construct(
        public string $propertyId,
        public string $blockId,
        public int $daysUntilEvent,
        public string $cutoffTier,
        public int $initialRooms,
        public int $currentAllocatedRooms,
        public int $confirmedPickupRooms,
        public int $unpickedBefore,
        public int $roomsReleasedToPublic,
        public int $remainingContractedRooms,
        public string $signatureHash
    ) {}
}
