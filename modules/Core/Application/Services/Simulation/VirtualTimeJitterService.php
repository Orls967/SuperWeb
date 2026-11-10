<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Algorithms\Simulation\StreamSeedAndFingerprint;

/**
 * Layanan penambahan variasi latensi (jitter) deterministik pada waktu eksekusi virtual.
 */
final class VirtualTimeJitterService
{
    public function __construct(
        private readonly StreamSeedAndFingerprint $streamRng,
    ) {}

    /**
     * Menghasilkan deviasi latensi mikrodetik deterministik (0 .. maxJitterMs).
     */
    public function computeJitterMs(int $baseSeed, int $dayNumber, string $subsystem, int $maxJitterMs = 50): int
    {
        $streamSeed = $this->streamRng->split($baseSeed, "jitter_{$subsystem}");
        $stepSeed = ($streamSeed + ($dayNumber * 65537)) % 2147483647;

        return $stepSeed % max(1, $maxJitterMs);
    }
}
