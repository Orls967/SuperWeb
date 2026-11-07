<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Personalization;

use InvalidArgumentException;

/**
 * Nilai Masukan Algoritma V16 PersonalizedOfferContextRouter.
 */
final readonly class PersonalizedOfferInput
{
    public function __construct(
        public int $userId,
        public int $currentZoneId,
        public string $membershipTierCode,
        public int $baseItemPriceIdr,
        public int $itemCogsFloorPriceIdr,
        public int $lastOfferTimestamp,
        public int $offersSentTonightCount,
        public int $currentTimestamp
    ) {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('User ID harus bernilai positif integer.');
        }
        if ($this->baseItemPriceIdr <= 0) {
            throw new InvalidArgumentException('Base item price IDR harus lebih besar dari nol.');
        }
    }
}
