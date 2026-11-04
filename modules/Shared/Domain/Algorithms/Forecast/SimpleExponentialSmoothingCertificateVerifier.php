<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Forecast;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

final class SimpleExponentialSmoothingCertificateVerifier
{
    public function verify(SimpleExponentialSmoothingResult $result): bool
    {
        $cert = $result->certificate;

        if (! isset($cert['is_valid']) || $cert['is_valid'] !== true) {
            throw new InvalidCertificateException('Certificate marked invalid');
        }

        if (($cert['level_formatted'] ?? null) !== $result->levelFormatted) {
            throw new InvalidCertificateException('Level formatted mismatch in certificate');
        }

        if (($cert['adjusted_level_formatted'] ?? null) !== $result->adjustedLevelFormatted) {
            throw new InvalidCertificateException('Adjusted level mismatch in certificate');
        }

        if (($cert['suggested_units'] ?? null) !== $result->suggestedUnitsPerDay) {
            throw new InvalidCertificateException('Suggested units mismatch in certificate');
        }

        $expectedPayload = sprintf(
            '%s|%d|%d|%s|%s|%d',
            implode(',', $result->demands),
            $result->alphaBps,
            $result->holidayFactorBps,
            $result->levelFormatted,
            $result->adjustedLevelFormatted,
            $result->suggestedUnitsPerDay
        );

        if (($cert['hash'] ?? null) !== hash('sha256', $expectedPayload)) {
            throw new InvalidCertificateException('Certificate hash mismatch');
        }

        return true;
    }
}
