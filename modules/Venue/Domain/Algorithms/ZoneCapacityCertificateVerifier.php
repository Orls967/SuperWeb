<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

use InvalidArgumentException;

final class ZoneCapacityCertificateVerifier
{
    private const SECRET = 'venue-zone-capacity-governor-v1';

    public static function sign(int $legal, int $margin, int $effective, int $rate, int $before, int $waiting, int $admitted, int $rejected, int $after): string
    {
        $payload = "{$legal}:{$margin}:{$effective}:{$rate}:{$before}:{$waiting}:{$admitted}:{$rejected}:{$after}";

        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public static function verify(ZoneCapacityCertificate $certificate): bool
    {
        $expectedSignature = self::sign(
            $certificate->legalCapacity,
            $certificate->safetyMarginBps,
            $certificate->effectiveCapacity,
            $certificate->ratePerMinute,
            $certificate->occupancyBefore,
            $certificate->waitingPatrons,
            $certificate->admittedPatrons,
            $certificate->rejectedPatrons,
            $certificate->occupancyAfter
        );

        if (! hash_equals($expectedSignature, $certificate->hmacSignature)) {
            throw new InvalidArgumentException('ZoneCapacityCertificate HMAC signature is invalid');
        }

        if ($certificate->occupancyAfter > $certificate->effectiveCapacity) {
            throw new InvalidArgumentException('ZoneCapacityCertificate invariant violated: occupancyAfter exceeds effectiveCapacity');
        }

        if ($certificate->admittedPatrons + $certificate->rejectedPatrons !== $certificate->waitingPatrons) {
            throw new InvalidArgumentException('ZoneCapacityCertificate invariant violated: admitted + rejected != waiting');
        }

        return true;
    }
}

