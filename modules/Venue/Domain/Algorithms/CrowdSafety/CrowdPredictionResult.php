<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\CrowdSafety;

/**
 * Hasil Keluaran Prediksi Lonjakan Kepadatan Kerumunan (V14).
 */
final readonly class CrowdPredictionResult
{
    public function __construct(
        public int $zoneId,
        public int $currentHeadcount,
        public int $predictedHeadcount15m,
        public int $predictedDensityBps,
        public string $recommendedAction,
        public bool $gateRestricted,
        public string $certificateSignature,
        public int $generatedAtTimestamp
    ) {}

    /**
     * Verifikasi integritas tanda tangan digital hasil prediksi.
     */
    public function verifySignature(string $saltSecret): bool
    {
        $payload = sprintf(
            '%d:%d:%d:%d:%s:%d:%d',
            $this->zoneId,
            $this->currentHeadcount,
            $this->predictedHeadcount15m,
            $this->predictedDensityBps,
            $this->recommendedAction,
            $this->gateRestricted ? 1 : 0,
            $this->generatedAtTimestamp
        );

        $expected = hash_hmac('sha256', $payload, $saltSecret);

        return hash_equals($expected, $this->certificateSignature);
    }
}
