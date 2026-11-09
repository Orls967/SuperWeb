<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Canary;

final class CanaryTrafficSplitController
{
    /**
     * Determines deterministically whether an incoming user session should be routed to canary.
     * Uses Murmur3/CRC32 hash of session identifier mod 100.
     */
    public function shouldRouteToCanary(string $sessionIdentifier, int $canaryPercentage): bool
    {
        if ($canaryPercentage <= 0) {
            return false;
        }
        if ($canaryPercentage >= 100) {
            return true;
        }

        $hash = crc32($sessionIdentifier);
        $bucket = abs($hash) % 100;

        return $bucket < $canaryPercentage;
    }
}
