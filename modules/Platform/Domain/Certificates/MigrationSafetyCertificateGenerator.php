<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Certificates;

final class MigrationSafetyCertificateGenerator
{
    public function generate(array $verifiedFiles, array $passedRules, int $violationCount, int $timestamp): MigrationSafetyCertificate
    {
        $certId = 'CERT-A5-'.strtoupper(bin2hex(random_bytes(8)));
        $payload = implode('|', [
            $certId,
            implode(',', $verifiedFiles),
            implode(',', $passedRules),
            (string) $violationCount,
            (string) $timestamp,
        ]);

        $hash = hash('sha256', $payload);

        return new MigrationSafetyCertificate(
            $certId,
            $verifiedFiles,
            $passedRules,
            $violationCount,
            $timestamp,
            $hash
        );
    }
}
