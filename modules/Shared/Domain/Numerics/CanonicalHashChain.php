<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;

/**
 * Standard Canonical Hash-Chain Generator (KONSEP.md §A14.5 / Standard S4).
 *
 * Payload is canonicalized by recursively sorting keys and converting all scalar values to string,
 * encoded with JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR.
 * Hash: sha256(prev_hash . "\n" . canonical_payload).
 * Genesis prev_hash is 64 zeros.
 *
 * Complexity: O(K log K) for key sorting + O(M) for sha256 of payload length M.
 */
final class CanonicalHashChain
{
    public const string GENESIS_PREV_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Converts arbitrary array payload into canonical JSON string.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidArgumentException
     */
    public static function canonicalize(array $payload): string
    {
        $normalized = self::normalizeRecursive($payload);

        return json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Computes the SHA-256 hash for a link in the chain.
     *
     * @param  string  $prevHash  64-hex previous hash or genesis (64 zeros)
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidArgumentException
     */
    public static function computeHash(string $prevHash, array $payload): string
    {
        if (strlen($prevHash) !== 64 || ! ctype_xdigit($prevHash)) {
            throw new InvalidArgumentException("Invalid previous hash format: expected 64 hex characters, got '{$prevHash}'.");
        }

        $canonical = self::canonicalize($payload);

        return hash('sha256', $prevHash."\n".$canonical);
    }

    private static function normalizeRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            if ($value === null) {
                return '';
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            return (string) $value;
        }

        $isAssoc = false;
        $i = 0;
        foreach ($value as $k => $v) {
            if ($k !== $i++) {
                $isAssoc = true;
                break;
            }
        }

        if ($isAssoc) {
            ksort($value, SORT_STRING);
            $normalized = [];
            foreach ($value as $k => $v) {
                $normalized[(string) $k] = self::normalizeRecursive($v);
            }

            return $normalized;
        }

        $normalized = [];
        foreach ($value as $v) {
            $normalized[] = self::normalizeRecursive($v);
        }

        return $normalized;
    }
}
