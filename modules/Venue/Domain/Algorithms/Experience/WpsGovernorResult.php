<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Experience;

/**
 * Hasil Keluaran Evaluasi Skor Kepuasan Antrean Layanan Bar / WPS (V15).
 */
final readonly class WpsGovernorResult
{
    public function __construct(
        public int $stationId,
        public int $averageWaitTimeMinutes,
        public int $utilizationRateBps,
        public int $wpsScore,
        public bool $reinforcementNeeded,
        public int $recommendedAdditionalServers,
        public string $certificateSignature,
        public int $calculatedAtTimestamp
    ) {}

    /**
     * Verifikasi integritas tanda tangan digital skor WPS.
     */
    public function verifySignature(string $saltSecret): bool
    {
        $payload = sprintf(
            '%d:%d:%d:%d:%d:%d:%d',
            $this->stationId,
            $this->averageWaitTimeMinutes,
            $this->utilizationRateBps,
            $this->wpsScore,
            $this->reinforcementNeeded ? 1 : 0,
            $this->recommendedAdditionalServers,
            $this->calculatedAtTimestamp
        );

        $expected = hash_hmac('sha256', $payload, $saltSecret);

        return hash_equals($expected, $this->certificateSignature);
    }
}
