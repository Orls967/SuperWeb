<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Certificates;

final class MigrationSafetyCertificateVerifier
{
    public function verify(MigrationSafetyCertificate $cert): bool
    {
        $payload = implode('|', [
            $cert->certificateId,
            implode(',', $cert->verifiedFiles),
            implode(',', $cert->passedRules),
            (string) $cert->violationCount,
            (string) $cert->timestamp,
        ]);

        $expected = hash('sha256', $payload);

        return hash_equals($expected, $cert->sha256Hash) && $cert->isValid();
    }
}
