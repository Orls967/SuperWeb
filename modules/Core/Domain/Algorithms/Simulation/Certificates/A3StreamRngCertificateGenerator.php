<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation\Certificates;

use Modules\Core\Domain\Algorithms\Simulation\StreamSeedAndFingerprint;

/**
 * Generator sertifikat stream seed dan hash chain A3.
 */
final class A3StreamRngCertificateGenerator
{
    /**
     * @param  array<int, string>  $streamNames
     * @param  array<int, array<string, mixed>>  $daySummaries
     * @return array{
     *     algorithm: string,
     *     base_seed: int,
     *     derived_streams: array<string, int>,
     *     final_chain_hash: string,
     *     certificate_hash: string
     * }
     */
    public function generateCertificate(int $baseSeed, array $streamNames, array $daySummaries): array
    {
        $rng = new StreamSeedAndFingerprint;

        $derived = [];
        foreach ($streamNames as $name) {
            $derived[$name] = $rng->split($baseSeed, $name);
        }

        $currentHash = $rng->genesisFingerprint($baseSeed);
        foreach ($daySummaries as $dayIdx => $summary) {
            $dayNumber = $dayIdx + 1;
            $currentHash = $rng->nextFingerprint($currentHash, $dayNumber, $summary);
        }

        $certHash = hash('sha256', "A3-CERT:{$baseSeed}:{$currentHash}");

        return [
            'algorithm' => 'A3_StreamSeedAndFingerprint',
            'base_seed' => $baseSeed,
            'derived_streams' => $derived,
            'final_chain_hash' => $currentHash,
            'certificate_hash' => $certHash,
        ];
    }
}
