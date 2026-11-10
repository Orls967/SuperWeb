<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Algorithms\Certificates;

use Modules\EnterpriseFinance\Domain\Exceptions\InvalidCertificateException;

final class SyndicatedLoanCovenantCertificateVerifier
{
    public function verify(SyndicatedLoanCovenantCertificate $certificate): bool
    {
        $payload = $certificate->payload;
        ksort($payload);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new InvalidCertificateException('Gagal melakukan serialisasi payload sertifikat covenant utang sindikasi.');
        }

        $expectedHash = hash('sha256', $json);
        if (! hash_equals($expectedHash, $certificate->sha256Signature)) {
            throw new InvalidCertificateException("Tanda tangan sertifikat covenant [{$certificate->sha256Signature}] tidak cocok dengan hash terhitung [{$expectedHash}].");
        }

        return true;
    }
}
