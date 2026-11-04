<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Stats;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

final class ModifiedZScoreCertificateVerifier
{
    public function verify(ModifiedZScoreResult $result): bool
    {
        $cert = $result->certificate;

        if (! isset($cert['is_valid']) || $cert['is_valid'] !== true) {
            throw new InvalidCertificateException('Certificate marked invalid');
        }

        if (($cert['computed_z'] ?? null) !== $result->zFormatted) {
            throw new InvalidCertificateException('Computed Z does not match certificate');
        }

        if (($cert['is_anomaly'] ?? null) !== $result->isAnomaly) {
            throw new InvalidCertificateException('Anomaly flag does not match certificate');
        }

        if (! isset($cert['hash']) || ! is_string($cert['hash'])) {
            throw new InvalidCertificateException('Certificate hash missing');
        }

        $expectedPayload = sprintf(
            '%d|%s|%s|%s|%s|%d',
            $result->x,
            $result->median->toFloat(),
            $result->mad->toFloat(),
            $result->zFormatted,
            $result->isAnomaly ? '1' : '0',
            count($result->values)
        );

        if (hash('sha256', $expectedPayload) !== $cert['hash']) {
            throw new InvalidCertificateException('Certificate hash mismatch');
        }

        return true;
    }
}

