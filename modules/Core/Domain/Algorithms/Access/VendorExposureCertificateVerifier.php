<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Access;

use InvalidArgumentException;

/**
 * Forensic certificate verifier for C3 VendorExposureAggregator (W8-D57).
 */
class VendorExposureCertificateVerifier
{
    /**
     * Verify that certificate has not been tampered with.
     *
     * @param  array<string, mixed>  $certificate
     */
    public function verifyCertificate(array $certificate): bool
    {
        $expectedSha = (string) ($certificate['sha256'] ?? '');
        if ($expectedSha === '') {
            throw new InvalidArgumentException('Sertifikat vendor exposure tidak memiliki tanda tangan sha256.');
        }

        $payload = $certificate;
        unset($payload['sha256']);

        $canonicalJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $computedSha = hash('sha256', $canonicalJson);

        if (! hash_equals($expectedSha, $computedSha)) {
            throw new InvalidArgumentException('VENDOR_EXPOSURE_CERT_TAMPERED: Tanda tangan SHA-256 sertifikat tidak cocok.');
        }

        return true;
    }
}
