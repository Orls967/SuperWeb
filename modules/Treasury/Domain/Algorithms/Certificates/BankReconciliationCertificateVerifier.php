<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Algorithms\Certificates;

use Modules\Treasury\Domain\Exceptions\InvalidCertificateException;

final class BankReconciliationCertificateVerifier
{
    public static function verify(BankReconciliationCertificate $certificate): bool
    {
        $inputs = $certificate->canonicalInputs;
        $results = $certificate->results;
        ksort($inputs);
        ksort($results);

        $payload = json_encode([
            'inputs' => $inputs,
            'steps' => $certificate->steps,
            'results' => $results,
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $payload);

        return hash_equals($expectedHash, $certificate->sha256);
    }

    public static function assertValid(BankReconciliationCertificate $certificate): void
    {
        if (! self::verify($certificate)) {
            throw new InvalidCertificateException('Hash sertifikat rekonsiliasi bank tidak cocok.');
        }
    }
}
