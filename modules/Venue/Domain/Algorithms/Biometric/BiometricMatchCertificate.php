<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Biometric;

/**
 * Sertifikat Kriptografis Hasil Pencocokan Biometrik & Liveness Check (V13).
 */
final readonly class BiometricMatchCertificate
{
    public function __construct(
        public int $userId,
        public bool $isMatched,
        public int $livenessScoreBps,
        public string $templateHash,
        public string $certificateSignature,
        public int $auditTimestamp
    ) {}

    /**
     * Verifikasi integritas tanda tangan digital sertifikat.
     */
    public function verifySignature(string $saltSecret): bool
    {
        $payload = sprintf(
            '%d:%d:%d:%s:%d',
            $this->userId,
            $this->isMatched ? 1 : 0,
            $this->livenessScoreBps,
            $this->templateHash,
            $this->auditTimestamp
        );

        $expectedSig = hash_hmac('sha256', $payload, $saltSecret);

        return hash_equals($expectedSig, $this->certificateSignature);
    }
}
