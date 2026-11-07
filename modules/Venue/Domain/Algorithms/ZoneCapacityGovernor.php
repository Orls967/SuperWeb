<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

/**
 * Algoritma V3: ZoneCapacityGovernor
 *
 * Mengatur admisi zona berbasis safety margin legal dan token bucket laju masuk per menit:
 * Kapasitas efektif = ⌊legal × (10.000 − margin bps) / 10.000⌋.
 * Admisi = min(antrian, kapasitas − okupansi, token).
 * Token bucket laju masuk per menit (kapasitas dan isi ulang = laju).
 */
final class ZoneCapacityGovernor
{
    public static function calculateEffectiveCapacity(int $legalCapacity, int $safetyMarginBps = 500): int
    {
        return intdiv($legalCapacity * (10000 - $safetyMarginBps), 10000);
    }

    /**
     * @return array{
     *     effective_capacity: int,
     *     admitted: int,
     *     rejected: int,
     *     occupancy_after: int,
     *     tokens_remaining: int,
     *     certificate: ZoneCapacityCertificate
     * }
     */
    public static function admit(
        int $legalCapacity,
        int $safetyMarginBps,
        int $ratePerMinute,
        int $currentOccupancy,
        int $waitingPatrons,
        ?int $availableTokens = null,
    ): array {
        $effectiveCapacity = self::calculateEffectiveCapacity($legalCapacity, $safetyMarginBps);
        $tokens = $availableTokens ?? $ratePerMinute;

        // Sisa kapasitas fisik menuju kapasitas efektif
        $capacityRemaining = max(0, $effectiveCapacity - $currentOccupancy);

        // Admisi = min(antrian, kapasitas − okupansi, token)
        $admitted = min($waitingPatrons, $capacityRemaining, $tokens);
        $rejected = $waitingPatrons - $admitted;
        $occupancyAfter = $currentOccupancy + $admitted;
        $tokensRemaining = $tokens - $admitted;

        $signature = ZoneCapacityCertificateVerifier::sign(
            $legalCapacity,
            $safetyMarginBps,
            $effectiveCapacity,
            $ratePerMinute,
            $currentOccupancy,
            $waitingPatrons,
            $admitted,
            $rejected,
            $occupancyAfter
        );

        $certificate = new ZoneCapacityCertificate(
            legalCapacity: $legalCapacity,
            safetyMarginBps: $safetyMarginBps,
            effectiveCapacity: $effectiveCapacity,
            ratePerMinute: $ratePerMinute,
            occupancyBefore: $currentOccupancy,
            waitingPatrons: $waitingPatrons,
            admittedPatrons: $admitted,
            rejectedPatrons: $rejected,
            occupancyAfter: $occupancyAfter,
            hmacSignature: $signature
        );

        ZoneCapacityCertificateVerifier::verify($certificate);

        return [
            'effective_capacity' => $effectiveCapacity,
            'admitted' => $admitted,
            'rejected' => $rejected,
            'occupancy_after' => $occupancyAfter,
            'tokens_remaining' => $tokensRemaining,
            'certificate' => $certificate,
        ];
    }
}

