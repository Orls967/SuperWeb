<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

/**
 * Data Nilai Sertifikat Kualifikasi Tier Loyalitas & Akumulasi Poin.
 */
final class LoyaltyTierCertificate
{
    /**
     * @param  array<string, mixed>  $privileges
     */
    public function __construct(
        public readonly string $guestId,
        public readonly int $totalNightsAccumulated,
        public readonly string $tierName,
        public readonly float $pointsMultiplier,
        public readonly int $pointsEarnedThisStay,
        public readonly int $totalActivePoints,
        public readonly array $privileges,
        public readonly string $checksum,
    ) {}
}
