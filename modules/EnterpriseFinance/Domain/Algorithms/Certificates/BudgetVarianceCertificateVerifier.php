<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Algorithms\Certificates;

use Modules\EnterpriseFinance\Domain\Exceptions\InvalidCertificateException;

final class BudgetVarianceCertificateVerifier
{
    public function verify(BudgetVarianceCertificate $certificate): bool
    {
        $payload = $certificate->payload;
        ksort($payload);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new InvalidCertificateException('Gagal melakukan serialisasi payload sertifikat analisis varians anggaran.');
        }

        $expectedHash = hash('sha256', $json);
        if (! hash_equals($expectedHash, $certificate->sha256Signature)) {
            throw new InvalidCertificateException("Tanda tangan sertifikat varians anggaran [{$certificate->sha256Signature}] tidak cocok dengan hash terhitung [{$expectedHash}].");
        }

        return true;
    }
}
