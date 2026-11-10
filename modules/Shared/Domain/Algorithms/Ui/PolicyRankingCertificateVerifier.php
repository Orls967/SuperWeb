<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Ui;

/**
 * Verifikator Sertifikat Algoritma A6 (Policy First Ranker).
 */
class PolicyRankingCertificateVerifier
{
    /**
     * Memverifikasi integritas sertifikat ranking policy-first.
     *
     * @param  array<string, mixed>  $certificate
     */
    public function verify(array $certificate): bool
    {
        if (! isset($certificate['algorithm'], $certificate['hash'], $certificate['payload'])) {
            return false;
        }

        if ($certificate['algorithm'] !== 'Policy-First-Ranker-V1') {
            return false;
        }

        try {
            $expectedHash = hash('sha256', json_encode($certificate['payload'], JSON_THROW_ON_ERROR));

            return hash_equals($expectedHash, (string) $certificate['hash']);
        } catch (\Throwable) {
            return false;
        }
    }
}
