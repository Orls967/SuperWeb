<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation\Verifiers;

/**
 * Verifier matematis kontinuitas rantai hash simulasi harian.
 * Formula:
 * h_0 = SHA-256("sim-run|seed=" . seed)
 * h_i = SHA-256(h_{i-1} . "|" . day . "|" . canonicalJson(summary))
 */
final class HashChainContinuityVerifier
{
    /**
     * @param  array<int, array{day: int, summary: array<string, mixed>, recorded_hash: string}>  $history
     * @return array{
     *     valid: bool,
     *     broken_at_day: int|null,
     *     expected_hash: string|null,
     *     actual_hash: string|null,
     *     message: string
     * }
     */
    public function verifyContinuity(int $seed, array $history): array
    {
        $currentHash = hash('sha256', "sim-run|seed={$seed}");

        foreach ($history as $entry) {
            $day = $entry['day'];
            $summary = $entry['summary'];
            $recordedHash = $entry['recorded_hash'];

            ksort($summary);
            $canonicalJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $expectedHash = hash('sha256', "{$currentHash}|{$day}|{$canonicalJson}");

            if (! hash_equals($expectedHash, $recordedHash)) {
                return [
                    'valid' => false,
                    'broken_at_day' => $day,
                    'expected_hash' => $expectedHash,
                    'actual_hash' => $recordedHash,
                    'message' => "Diskrepansi rantai hash pada hari ke-{$day}: hash tercatat tidak cocok dengan evaluasi SHA-256 kanonikal.",
                ];
            }

            $currentHash = $expectedHash;
        }

        return [
            'valid' => true,
            'broken_at_day' => null,
            'expected_hash' => null,
            'actual_hash' => null,
            'message' => 'Seluruh rantai hash harian kontinu dan valid secara kriptografis.',
        ];
    }
}
