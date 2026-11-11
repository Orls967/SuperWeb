<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms;

use InvalidArgumentException;

/**
 * Shared helper for deterministic canonical JSON SHA-256 certificates (W8-P4, PR2).
 * Recursively sorts associative keys manually before hashing with built-in PHP SHA-256.
 *
 * Complexity: O(K log K) on total JSON keys. Integer/string canonical encoding only.
 */
final class CanonicalCertificateSupport
{
    /**
     * Build a verifiable canonical certificate containing input, steps, result, and sha256.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>  $steps
     * @param  array<string, mixed>  $result
     * @return array{input: array<string, mixed>, steps: list<string>, result: array<string, mixed>, sha256: string}
     */
    public static function issue(array $input, array $steps, array $result): array
    {
        $canonicalInput = self::canonicalize($input);
        $canonicalSteps = array_values($steps);
        $canonicalResult = self::canonicalize($result);

        $hash = self::computeHash($canonicalInput, $canonicalSteps, $canonicalResult);

        return [
            'input' => $canonicalInput,
            'steps' => $canonicalSteps,
            'result' => $canonicalResult,
            'sha256' => $hash,
        ];
    }

    /**
     * Verify certificate structure and cryptographic SHA-256 digest.
     * Throws InvalidArgumentException on corrupted or tampered certificates.
     *
     * @param  array<string, mixed>  $certificate
     * @return array{input: array<string, mixed>, steps: list<mixed>, result: array<string, mixed>}
     */
    public static function verifyIntegrity(array $certificate, string $codePrefix = 'CERT'): array
    {
        if (! isset($certificate['input'], $certificate['steps'], $certificate['result'], $certificate['sha256'])
            || ! is_array($certificate['input'])
            || ! is_array($certificate['steps'])
            || ! is_array($certificate['result'])
            || ! is_string($certificate['sha256'])) {
            throw new InvalidArgumentException("{$codePrefix}_CORRUPT: Struktur sertifikat tidak lengkap atau rusak.");
        }

        $canonicalInput = self::canonicalize($certificate['input']);
        $canonicalSteps = array_values($certificate['steps']);
        $canonicalResult = self::canonicalize($certificate['result']);
        $expectedHash = self::computeHash($canonicalInput, $canonicalSteps, $canonicalResult);

        if (! hash_equals($expectedHash, $certificate['sha256'])) {
            throw new InvalidArgumentException("{$codePrefix}_HASH_MISMATCH: Digest SHA-256 sertifikat tidak cocok.");
        }

        return [
            'input' => $canonicalInput,
            'steps' => $canonicalSteps,
            'result' => $canonicalResult,
        ];
    }

    /**
     * Canonicalize array recursively by sorting associative keys manually in ascending string order.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function canonicalize(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data, SORT_STRING);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::canonicalize($value);
            }
        }

        return $data;
    }

    /**
     * @param  array<mixed>  $input
     * @param  list<mixed>  $steps
     * @param  array<mixed>  $result
     */
    private static function computeHash(array $input, array $steps, array $result): string
    {
        $payload = [
            'input' => $input,
            'steps' => $steps,
            'result' => $result,
        ];

        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $json);
    }
}
