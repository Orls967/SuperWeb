<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Algorithms\Certificates;

use Modules\EnterpriseFinance\Domain\Exceptions\InvalidCertificateException;

final class ConsolidatedTrialBalanceCertificateVerifier
{
    public function verify(ConsolidatedTrialBalanceCertificate $certificate): bool
    {
        $payload = $certificate->payload;
        ksort($payload);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new InvalidCertificateException('Gagal melakukan serialisasi payload sertifikat neraca saldo konsolidasi.');
        }

        $expectedHash = hash('sha256', $json);
        if (! hash_equals($expectedHash, $certificate->sha256Signature)) {
            throw new InvalidCertificateException("Tanda tangan sertifikat neraca saldo konsolidasi [{$certificate->sha256Signature}] tidak cocok dengan hash terhitung [{$expectedHash}].");
        }

        return true;
    }
}
