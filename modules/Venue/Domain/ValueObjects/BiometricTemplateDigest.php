<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Nilai Objek Ringkasan Templat Biometrik Kriptografis (Zero-Knowledge Digest).
 * Menjamin integritas hash satu arah templat wajah dan mencegah penyimpanan vektor mentah.
 */
final readonly class BiometricTemplateDigest
{
    /**
     * @param  string  $templateHash  Hash HMAC-SHA256 64-karakter
     * @param  string  $saltKeyId  Pengenal kunci garam per-venue
     * @param  int  $livenessScoreBps  Skor kepercayaan liveness (0 - 10000)
     * @param  int  $enrolledAtTimestamp  Waktu pendaftaran (Unix)
     * @param  string  $sensorModel  Identifikasi perangkat keras kamera
     */
    public function __construct(
        public string $templateHash,
        public string $saltKeyId,
        public int $livenessScoreBps,
        public int $enrolledAtTimestamp,
        public string $sensorModel
    ) {
        if (strlen($this->templateHash) !== 64 || ! ctype_xdigit($this->templateHash)) {
            throw new InvalidArgumentException('Template hash harus berupa 64 karakter hexadesimal sha256 valid.');
        }
        if ($this->livenessScoreBps < 0 || $this->livenessScoreBps > 10000) {
            throw new InvalidArgumentException('Liveness score bps harus berada pada rentang 0 hingga 10000.');
        }
    }

    /**
     * Pabrikasi templat digest dari vektor fitur di memori dan garam rahasia.
     */
    public static function createFromFeatureVector(
        int $userId,
        string $rawVectorInMemory,
        string $saltSecret,
        string $saltKeyId,
        int $livenessScoreBps,
        string $sensorModel
    ): self {
        $hash = hash_hmac('sha256', "BIO-RAW:{$userId}:{$rawVectorInMemory}:{$saltKeyId}", $saltSecret);

        return new self(
            templateHash: $hash,
            saltKeyId: $saltKeyId,
            livenessScoreBps: $livenessScoreBps,
            enrolledAtTimestamp: time(),
            sensorModel: $sensorModel
        );
    }

    /**
     * Verifikasi kecocokan probe hash dengan timing-attack safe comparison.
     */
    public function verifyProbe(string $probeHash): bool
    {
        return hash_equals($this->templateHash, $probeHash);
    }

    /**
     * Representasi array untuk serialisasi aman audit trail.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'template_hash_preview' => substr($this->templateHash, 0, 16).'...',
            'salt_key_id' => $this->saltKeyId,
            'liveness_score_bps' => $this->livenessScoreBps,
            'enrolled_at_timestamp' => $this->enrolledAtTimestamp,
            'sensor_model' => $this->sensorModel,
            'privacy_compliant' => true,
        ];
    }
}
